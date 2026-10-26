// GENERATED CODE - DO NOT MODIFY BY HAND
// coverage:ignore-file
// ignore_for_file: type=lint
// ignore_for_file: unused_element, deprecated_member_use, deprecated_member_use_from_same_package, use_function_type_syntax_for_parameters, unnecessary_const, avoid_init_to_null, invalid_override_different_default_values_named, prefer_expression_function_bodies, annotate_overrides, invalid_annotation_target, unnecessary_question_mark

part of 'counselor.dart';

// **************************************************************************
// FreezedGenerator
// **************************************************************************

// dart format off
T _$identity<T>(T value) => value;

/// @nodoc
mixin _$CounselorContext {

@JsonKey(unknownEnumValue: ContextType.unknown) ContextType get type; String? get organization;
/// Create a copy of CounselorContext
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$CounselorContextCopyWith<CounselorContext> get copyWith => _$CounselorContextCopyWithImpl<CounselorContext>(this as CounselorContext, _$identity);

  /// Serializes this CounselorContext to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is CounselorContext&&(identical(other.type, type) || other.type == type)&&(identical(other.organization, organization) || other.organization == organization));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,type,organization);

@override
String toString() {
  return 'CounselorContext(type: $type, organization: $organization)';
}


}

/// @nodoc
abstract mixin class $CounselorContextCopyWith<$Res>  {
  factory $CounselorContextCopyWith(CounselorContext value, $Res Function(CounselorContext) _then) = _$CounselorContextCopyWithImpl;
@useResult
$Res call({
@JsonKey(unknownEnumValue: ContextType.unknown) ContextType type, String? organization
});




}
/// @nodoc
class _$CounselorContextCopyWithImpl<$Res>
    implements $CounselorContextCopyWith<$Res> {
  _$CounselorContextCopyWithImpl(this._self, this._then);

  final CounselorContext _self;
  final $Res Function(CounselorContext) _then;

/// Create a copy of CounselorContext
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? type = null,Object? organization = freezed,}) {
  return _then(_self.copyWith(
type: null == type ? _self.type : type // ignore: cast_nullable_to_non_nullable
as ContextType,organization: freezed == organization ? _self.organization : organization // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}

}


/// Adds pattern-matching-related methods to [CounselorContext].
extension CounselorContextPatterns on CounselorContext {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _CounselorContext value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _CounselorContext() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _CounselorContext value)  $default,){
final _that = this;
switch (_that) {
case _CounselorContext():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _CounselorContext value)?  $default,){
final _that = this;
switch (_that) {
case _CounselorContext() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function(@JsonKey(unknownEnumValue: ContextType.unknown)  ContextType type,  String? organization)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _CounselorContext() when $default != null:
return $default(_that.type,_that.organization);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function(@JsonKey(unknownEnumValue: ContextType.unknown)  ContextType type,  String? organization)  $default,) {final _that = this;
switch (_that) {
case _CounselorContext():
return $default(_that.type,_that.organization);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function(@JsonKey(unknownEnumValue: ContextType.unknown)  ContextType type,  String? organization)?  $default,) {final _that = this;
switch (_that) {
case _CounselorContext() when $default != null:
return $default(_that.type,_that.organization);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _CounselorContext implements CounselorContext {
  const _CounselorContext({@JsonKey(unknownEnumValue: ContextType.unknown) required this.type, this.organization});
  factory _CounselorContext.fromJson(Map<String, dynamic> json) => _$CounselorContextFromJson(json);

@override@JsonKey(unknownEnumValue: ContextType.unknown) final  ContextType type;
@override final  String? organization;

/// Create a copy of CounselorContext
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$CounselorContextCopyWith<_CounselorContext> get copyWith => __$CounselorContextCopyWithImpl<_CounselorContext>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$CounselorContextToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _CounselorContext&&(identical(other.type, type) || other.type == type)&&(identical(other.organization, organization) || other.organization == organization));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,type,organization);

@override
String toString() {
  return 'CounselorContext(type: $type, organization: $organization)';
}


}

/// @nodoc
abstract mixin class _$CounselorContextCopyWith<$Res> implements $CounselorContextCopyWith<$Res> {
  factory _$CounselorContextCopyWith(_CounselorContext value, $Res Function(_CounselorContext) _then) = __$CounselorContextCopyWithImpl;
@override @useResult
$Res call({
@JsonKey(unknownEnumValue: ContextType.unknown) ContextType type, String? organization
});




}
/// @nodoc
class __$CounselorContextCopyWithImpl<$Res>
    implements _$CounselorContextCopyWith<$Res> {
  __$CounselorContextCopyWithImpl(this._self, this._then);

  final _CounselorContext _self;
  final $Res Function(_CounselorContext) _then;

/// Create a copy of CounselorContext
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? type = null,Object? organization = freezed,}) {
  return _then(_CounselorContext(
type: null == type ? _self.type : type // ignore: cast_nullable_to_non_nullable
as ContextType,organization: freezed == organization ? _self.organization : organization // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}


}


/// @nodoc
mixin _$CounselorLocation {

 String get city; String get state;
/// Create a copy of CounselorLocation
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$CounselorLocationCopyWith<CounselorLocation> get copyWith => _$CounselorLocationCopyWithImpl<CounselorLocation>(this as CounselorLocation, _$identity);

  /// Serializes this CounselorLocation to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is CounselorLocation&&(identical(other.city, city) || other.city == city)&&(identical(other.state, state) || other.state == state));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,city,state);

@override
String toString() {
  return 'CounselorLocation(city: $city, state: $state)';
}


}

/// @nodoc
abstract mixin class $CounselorLocationCopyWith<$Res>  {
  factory $CounselorLocationCopyWith(CounselorLocation value, $Res Function(CounselorLocation) _then) = _$CounselorLocationCopyWithImpl;
@useResult
$Res call({
 String city, String state
});




}
/// @nodoc
class _$CounselorLocationCopyWithImpl<$Res>
    implements $CounselorLocationCopyWith<$Res> {
  _$CounselorLocationCopyWithImpl(this._self, this._then);

  final CounselorLocation _self;
  final $Res Function(CounselorLocation) _then;

/// Create a copy of CounselorLocation
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? city = null,Object? state = null,}) {
  return _then(_self.copyWith(
city: null == city ? _self.city : city // ignore: cast_nullable_to_non_nullable
as String,state: null == state ? _self.state : state // ignore: cast_nullable_to_non_nullable
as String,
  ));
}

}


/// Adds pattern-matching-related methods to [CounselorLocation].
extension CounselorLocationPatterns on CounselorLocation {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _CounselorLocation value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _CounselorLocation() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _CounselorLocation value)  $default,){
final _that = this;
switch (_that) {
case _CounselorLocation():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _CounselorLocation value)?  $default,){
final _that = this;
switch (_that) {
case _CounselorLocation() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( String city,  String state)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _CounselorLocation() when $default != null:
return $default(_that.city,_that.state);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( String city,  String state)  $default,) {final _that = this;
switch (_that) {
case _CounselorLocation():
return $default(_that.city,_that.state);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( String city,  String state)?  $default,) {final _that = this;
switch (_that) {
case _CounselorLocation() when $default != null:
return $default(_that.city,_that.state);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _CounselorLocation implements CounselorLocation {
  const _CounselorLocation({required this.city, required this.state});
  factory _CounselorLocation.fromJson(Map<String, dynamic> json) => _$CounselorLocationFromJson(json);

@override final  String city;
@override final  String state;

/// Create a copy of CounselorLocation
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$CounselorLocationCopyWith<_CounselorLocation> get copyWith => __$CounselorLocationCopyWithImpl<_CounselorLocation>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$CounselorLocationToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _CounselorLocation&&(identical(other.city, city) || other.city == city)&&(identical(other.state, state) || other.state == state));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,city,state);

@override
String toString() {
  return 'CounselorLocation(city: $city, state: $state)';
}


}

/// @nodoc
abstract mixin class _$CounselorLocationCopyWith<$Res> implements $CounselorLocationCopyWith<$Res> {
  factory _$CounselorLocationCopyWith(_CounselorLocation value, $Res Function(_CounselorLocation) _then) = __$CounselorLocationCopyWithImpl;
@override @useResult
$Res call({
 String city, String state
});




}
/// @nodoc
class __$CounselorLocationCopyWithImpl<$Res>
    implements _$CounselorLocationCopyWith<$Res> {
  __$CounselorLocationCopyWithImpl(this._self, this._then);

  final _CounselorLocation _self;
  final $Res Function(_CounselorLocation) _then;

/// Create a copy of CounselorLocation
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? city = null,Object? state = null,}) {
  return _then(_CounselorLocation(
city: null == city ? _self.city : city // ignore: cast_nullable_to_non_nullable
as String,state: null == state ? _self.state : state // ignore: cast_nullable_to_non_nullable
as String,
  ));
}


}


/// @nodoc
mixin _$CounselorSlot {

 DateTime get startsAt; int get durationMinutes;@JsonKey(unknownEnumValue: SessionMode.unknown) SessionMode get sessionMode; String? get city; String? get state; CounselorContext? get context;
/// Create a copy of CounselorSlot
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$CounselorSlotCopyWith<CounselorSlot> get copyWith => _$CounselorSlotCopyWithImpl<CounselorSlot>(this as CounselorSlot, _$identity);

  /// Serializes this CounselorSlot to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is CounselorSlot&&(identical(other.startsAt, startsAt) || other.startsAt == startsAt)&&(identical(other.durationMinutes, durationMinutes) || other.durationMinutes == durationMinutes)&&(identical(other.sessionMode, sessionMode) || other.sessionMode == sessionMode)&&(identical(other.city, city) || other.city == city)&&(identical(other.state, state) || other.state == state)&&(identical(other.context, context) || other.context == context));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,startsAt,durationMinutes,sessionMode,city,state,context);

@override
String toString() {
  return 'CounselorSlot(startsAt: $startsAt, durationMinutes: $durationMinutes, sessionMode: $sessionMode, city: $city, state: $state, context: $context)';
}


}

/// @nodoc
abstract mixin class $CounselorSlotCopyWith<$Res>  {
  factory $CounselorSlotCopyWith(CounselorSlot value, $Res Function(CounselorSlot) _then) = _$CounselorSlotCopyWithImpl;
@useResult
$Res call({
 DateTime startsAt, int durationMinutes,@JsonKey(unknownEnumValue: SessionMode.unknown) SessionMode sessionMode, String? city, String? state, CounselorContext? context
});


$CounselorContextCopyWith<$Res>? get context;

}
/// @nodoc
class _$CounselorSlotCopyWithImpl<$Res>
    implements $CounselorSlotCopyWith<$Res> {
  _$CounselorSlotCopyWithImpl(this._self, this._then);

  final CounselorSlot _self;
  final $Res Function(CounselorSlot) _then;

/// Create a copy of CounselorSlot
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? startsAt = null,Object? durationMinutes = null,Object? sessionMode = null,Object? city = freezed,Object? state = freezed,Object? context = freezed,}) {
  return _then(_self.copyWith(
startsAt: null == startsAt ? _self.startsAt : startsAt // ignore: cast_nullable_to_non_nullable
as DateTime,durationMinutes: null == durationMinutes ? _self.durationMinutes : durationMinutes // ignore: cast_nullable_to_non_nullable
as int,sessionMode: null == sessionMode ? _self.sessionMode : sessionMode // ignore: cast_nullable_to_non_nullable
as SessionMode,city: freezed == city ? _self.city : city // ignore: cast_nullable_to_non_nullable
as String?,state: freezed == state ? _self.state : state // ignore: cast_nullable_to_non_nullable
as String?,context: freezed == context ? _self.context : context // ignore: cast_nullable_to_non_nullable
as CounselorContext?,
  ));
}
/// Create a copy of CounselorSlot
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$CounselorContextCopyWith<$Res>? get context {
    if (_self.context == null) {
    return null;
  }

  return $CounselorContextCopyWith<$Res>(_self.context!, (value) {
    return _then(_self.copyWith(context: value));
  });
}
}


/// Adds pattern-matching-related methods to [CounselorSlot].
extension CounselorSlotPatterns on CounselorSlot {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _CounselorSlot value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _CounselorSlot() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _CounselorSlot value)  $default,){
final _that = this;
switch (_that) {
case _CounselorSlot():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _CounselorSlot value)?  $default,){
final _that = this;
switch (_that) {
case _CounselorSlot() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( DateTime startsAt,  int durationMinutes, @JsonKey(unknownEnumValue: SessionMode.unknown)  SessionMode sessionMode,  String? city,  String? state,  CounselorContext? context)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _CounselorSlot() when $default != null:
return $default(_that.startsAt,_that.durationMinutes,_that.sessionMode,_that.city,_that.state,_that.context);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( DateTime startsAt,  int durationMinutes, @JsonKey(unknownEnumValue: SessionMode.unknown)  SessionMode sessionMode,  String? city,  String? state,  CounselorContext? context)  $default,) {final _that = this;
switch (_that) {
case _CounselorSlot():
return $default(_that.startsAt,_that.durationMinutes,_that.sessionMode,_that.city,_that.state,_that.context);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( DateTime startsAt,  int durationMinutes, @JsonKey(unknownEnumValue: SessionMode.unknown)  SessionMode sessionMode,  String? city,  String? state,  CounselorContext? context)?  $default,) {final _that = this;
switch (_that) {
case _CounselorSlot() when $default != null:
return $default(_that.startsAt,_that.durationMinutes,_that.sessionMode,_that.city,_that.state,_that.context);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _CounselorSlot implements CounselorSlot {
  const _CounselorSlot({required this.startsAt, required this.durationMinutes, @JsonKey(unknownEnumValue: SessionMode.unknown) required this.sessionMode, this.city, this.state, this.context});
  factory _CounselorSlot.fromJson(Map<String, dynamic> json) => _$CounselorSlotFromJson(json);

@override final  DateTime startsAt;
@override final  int durationMinutes;
@override@JsonKey(unknownEnumValue: SessionMode.unknown) final  SessionMode sessionMode;
@override final  String? city;
@override final  String? state;
@override final  CounselorContext? context;

/// Create a copy of CounselorSlot
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$CounselorSlotCopyWith<_CounselorSlot> get copyWith => __$CounselorSlotCopyWithImpl<_CounselorSlot>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$CounselorSlotToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _CounselorSlot&&(identical(other.startsAt, startsAt) || other.startsAt == startsAt)&&(identical(other.durationMinutes, durationMinutes) || other.durationMinutes == durationMinutes)&&(identical(other.sessionMode, sessionMode) || other.sessionMode == sessionMode)&&(identical(other.city, city) || other.city == city)&&(identical(other.state, state) || other.state == state)&&(identical(other.context, context) || other.context == context));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,startsAt,durationMinutes,sessionMode,city,state,context);

@override
String toString() {
  return 'CounselorSlot(startsAt: $startsAt, durationMinutes: $durationMinutes, sessionMode: $sessionMode, city: $city, state: $state, context: $context)';
}


}

/// @nodoc
abstract mixin class _$CounselorSlotCopyWith<$Res> implements $CounselorSlotCopyWith<$Res> {
  factory _$CounselorSlotCopyWith(_CounselorSlot value, $Res Function(_CounselorSlot) _then) = __$CounselorSlotCopyWithImpl;
@override @useResult
$Res call({
 DateTime startsAt, int durationMinutes,@JsonKey(unknownEnumValue: SessionMode.unknown) SessionMode sessionMode, String? city, String? state, CounselorContext? context
});


@override $CounselorContextCopyWith<$Res>? get context;

}
/// @nodoc
class __$CounselorSlotCopyWithImpl<$Res>
    implements _$CounselorSlotCopyWith<$Res> {
  __$CounselorSlotCopyWithImpl(this._self, this._then);

  final _CounselorSlot _self;
  final $Res Function(_CounselorSlot) _then;

/// Create a copy of CounselorSlot
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? startsAt = null,Object? durationMinutes = null,Object? sessionMode = null,Object? city = freezed,Object? state = freezed,Object? context = freezed,}) {
  return _then(_CounselorSlot(
startsAt: null == startsAt ? _self.startsAt : startsAt // ignore: cast_nullable_to_non_nullable
as DateTime,durationMinutes: null == durationMinutes ? _self.durationMinutes : durationMinutes // ignore: cast_nullable_to_non_nullable
as int,sessionMode: null == sessionMode ? _self.sessionMode : sessionMode // ignore: cast_nullable_to_non_nullable
as SessionMode,city: freezed == city ? _self.city : city // ignore: cast_nullable_to_non_nullable
as String?,state: freezed == state ? _self.state : state // ignore: cast_nullable_to_non_nullable
as String?,context: freezed == context ? _self.context : context // ignore: cast_nullable_to_non_nullable
as CounselorContext?,
  ));
}

/// Create a copy of CounselorSlot
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$CounselorContextCopyWith<$Res>? get context {
    if (_self.context == null) {
    return null;
  }

  return $CounselorContextCopyWith<$Res>(_self.context!, (value) {
    return _then(_self.copyWith(context: value));
  });
}
}


/// @nodoc
mixin _$Counselor {

 String get id; String get name; bool get verified; List<CounselorContext> get contexts; List<String> get specializations; int get specializationsCount; List<String> get languages;@JsonKey(unknownEnumValue: SessionMode.unknown) List<SessionMode> get sessionModes; List<CounselorLocation> get locations; CounselorSlot? get nextAvailable;/// Profile page only.
 bool? get acceptsRequests; List<CounselorSlot> get upcomingSlots;
/// Create a copy of Counselor
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$CounselorCopyWith<Counselor> get copyWith => _$CounselorCopyWithImpl<Counselor>(this as Counselor, _$identity);

  /// Serializes this Counselor to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is Counselor&&(identical(other.id, id) || other.id == id)&&(identical(other.name, name) || other.name == name)&&(identical(other.verified, verified) || other.verified == verified)&&const DeepCollectionEquality().equals(other.contexts, contexts)&&const DeepCollectionEquality().equals(other.specializations, specializations)&&(identical(other.specializationsCount, specializationsCount) || other.specializationsCount == specializationsCount)&&const DeepCollectionEquality().equals(other.languages, languages)&&const DeepCollectionEquality().equals(other.sessionModes, sessionModes)&&const DeepCollectionEquality().equals(other.locations, locations)&&(identical(other.nextAvailable, nextAvailable) || other.nextAvailable == nextAvailable)&&(identical(other.acceptsRequests, acceptsRequests) || other.acceptsRequests == acceptsRequests)&&const DeepCollectionEquality().equals(other.upcomingSlots, upcomingSlots));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,name,verified,const DeepCollectionEquality().hash(contexts),const DeepCollectionEquality().hash(specializations),specializationsCount,const DeepCollectionEquality().hash(languages),const DeepCollectionEquality().hash(sessionModes),const DeepCollectionEquality().hash(locations),nextAvailable,acceptsRequests,const DeepCollectionEquality().hash(upcomingSlots));

@override
String toString() {
  return 'Counselor(id: $id, name: $name, verified: $verified, contexts: $contexts, specializations: $specializations, specializationsCount: $specializationsCount, languages: $languages, sessionModes: $sessionModes, locations: $locations, nextAvailable: $nextAvailable, acceptsRequests: $acceptsRequests, upcomingSlots: $upcomingSlots)';
}


}

/// @nodoc
abstract mixin class $CounselorCopyWith<$Res>  {
  factory $CounselorCopyWith(Counselor value, $Res Function(Counselor) _then) = _$CounselorCopyWithImpl;
@useResult
$Res call({
 String id, String name, bool verified, List<CounselorContext> contexts, List<String> specializations, int specializationsCount, List<String> languages,@JsonKey(unknownEnumValue: SessionMode.unknown) List<SessionMode> sessionModes, List<CounselorLocation> locations, CounselorSlot? nextAvailable, bool? acceptsRequests, List<CounselorSlot> upcomingSlots
});


$CounselorSlotCopyWith<$Res>? get nextAvailable;

}
/// @nodoc
class _$CounselorCopyWithImpl<$Res>
    implements $CounselorCopyWith<$Res> {
  _$CounselorCopyWithImpl(this._self, this._then);

  final Counselor _self;
  final $Res Function(Counselor) _then;

/// Create a copy of Counselor
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? id = null,Object? name = null,Object? verified = null,Object? contexts = null,Object? specializations = null,Object? specializationsCount = null,Object? languages = null,Object? sessionModes = null,Object? locations = null,Object? nextAvailable = freezed,Object? acceptsRequests = freezed,Object? upcomingSlots = null,}) {
  return _then(_self.copyWith(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as String,name: null == name ? _self.name : name // ignore: cast_nullable_to_non_nullable
as String,verified: null == verified ? _self.verified : verified // ignore: cast_nullable_to_non_nullable
as bool,contexts: null == contexts ? _self.contexts : contexts // ignore: cast_nullable_to_non_nullable
as List<CounselorContext>,specializations: null == specializations ? _self.specializations : specializations // ignore: cast_nullable_to_non_nullable
as List<String>,specializationsCount: null == specializationsCount ? _self.specializationsCount : specializationsCount // ignore: cast_nullable_to_non_nullable
as int,languages: null == languages ? _self.languages : languages // ignore: cast_nullable_to_non_nullable
as List<String>,sessionModes: null == sessionModes ? _self.sessionModes : sessionModes // ignore: cast_nullable_to_non_nullable
as List<SessionMode>,locations: null == locations ? _self.locations : locations // ignore: cast_nullable_to_non_nullable
as List<CounselorLocation>,nextAvailable: freezed == nextAvailable ? _self.nextAvailable : nextAvailable // ignore: cast_nullable_to_non_nullable
as CounselorSlot?,acceptsRequests: freezed == acceptsRequests ? _self.acceptsRequests : acceptsRequests // ignore: cast_nullable_to_non_nullable
as bool?,upcomingSlots: null == upcomingSlots ? _self.upcomingSlots : upcomingSlots // ignore: cast_nullable_to_non_nullable
as List<CounselorSlot>,
  ));
}
/// Create a copy of Counselor
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$CounselorSlotCopyWith<$Res>? get nextAvailable {
    if (_self.nextAvailable == null) {
    return null;
  }

  return $CounselorSlotCopyWith<$Res>(_self.nextAvailable!, (value) {
    return _then(_self.copyWith(nextAvailable: value));
  });
}
}


/// Adds pattern-matching-related methods to [Counselor].
extension CounselorPatterns on Counselor {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _Counselor value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _Counselor() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _Counselor value)  $default,){
final _that = this;
switch (_that) {
case _Counselor():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _Counselor value)?  $default,){
final _that = this;
switch (_that) {
case _Counselor() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( String id,  String name,  bool verified,  List<CounselorContext> contexts,  List<String> specializations,  int specializationsCount,  List<String> languages, @JsonKey(unknownEnumValue: SessionMode.unknown)  List<SessionMode> sessionModes,  List<CounselorLocation> locations,  CounselorSlot? nextAvailable,  bool? acceptsRequests,  List<CounselorSlot> upcomingSlots)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _Counselor() when $default != null:
return $default(_that.id,_that.name,_that.verified,_that.contexts,_that.specializations,_that.specializationsCount,_that.languages,_that.sessionModes,_that.locations,_that.nextAvailable,_that.acceptsRequests,_that.upcomingSlots);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( String id,  String name,  bool verified,  List<CounselorContext> contexts,  List<String> specializations,  int specializationsCount,  List<String> languages, @JsonKey(unknownEnumValue: SessionMode.unknown)  List<SessionMode> sessionModes,  List<CounselorLocation> locations,  CounselorSlot? nextAvailable,  bool? acceptsRequests,  List<CounselorSlot> upcomingSlots)  $default,) {final _that = this;
switch (_that) {
case _Counselor():
return $default(_that.id,_that.name,_that.verified,_that.contexts,_that.specializations,_that.specializationsCount,_that.languages,_that.sessionModes,_that.locations,_that.nextAvailable,_that.acceptsRequests,_that.upcomingSlots);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( String id,  String name,  bool verified,  List<CounselorContext> contexts,  List<String> specializations,  int specializationsCount,  List<String> languages, @JsonKey(unknownEnumValue: SessionMode.unknown)  List<SessionMode> sessionModes,  List<CounselorLocation> locations,  CounselorSlot? nextAvailable,  bool? acceptsRequests,  List<CounselorSlot> upcomingSlots)?  $default,) {final _that = this;
switch (_that) {
case _Counselor() when $default != null:
return $default(_that.id,_that.name,_that.verified,_that.contexts,_that.specializations,_that.specializationsCount,_that.languages,_that.sessionModes,_that.locations,_that.nextAvailable,_that.acceptsRequests,_that.upcomingSlots);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _Counselor implements Counselor {
  const _Counselor({required this.id, required this.name, this.verified = true, final  List<CounselorContext> contexts = const [], final  List<String> specializations = const [], this.specializationsCount = 0, final  List<String> languages = const [], @JsonKey(unknownEnumValue: SessionMode.unknown) final  List<SessionMode> sessionModes = const [], final  List<CounselorLocation> locations = const [], this.nextAvailable, this.acceptsRequests, final  List<CounselorSlot> upcomingSlots = const []}): _contexts = contexts,_specializations = specializations,_languages = languages,_sessionModes = sessionModes,_locations = locations,_upcomingSlots = upcomingSlots;
  factory _Counselor.fromJson(Map<String, dynamic> json) => _$CounselorFromJson(json);

@override final  String id;
@override final  String name;
@override@JsonKey() final  bool verified;
 final  List<CounselorContext> _contexts;
@override@JsonKey() List<CounselorContext> get contexts {
  if (_contexts is EqualUnmodifiableListView) return _contexts;
  // ignore: implicit_dynamic_type
  return EqualUnmodifiableListView(_contexts);
}

 final  List<String> _specializations;
@override@JsonKey() List<String> get specializations {
  if (_specializations is EqualUnmodifiableListView) return _specializations;
  // ignore: implicit_dynamic_type
  return EqualUnmodifiableListView(_specializations);
}

@override@JsonKey() final  int specializationsCount;
 final  List<String> _languages;
@override@JsonKey() List<String> get languages {
  if (_languages is EqualUnmodifiableListView) return _languages;
  // ignore: implicit_dynamic_type
  return EqualUnmodifiableListView(_languages);
}

 final  List<SessionMode> _sessionModes;
@override@JsonKey(unknownEnumValue: SessionMode.unknown) List<SessionMode> get sessionModes {
  if (_sessionModes is EqualUnmodifiableListView) return _sessionModes;
  // ignore: implicit_dynamic_type
  return EqualUnmodifiableListView(_sessionModes);
}

 final  List<CounselorLocation> _locations;
@override@JsonKey() List<CounselorLocation> get locations {
  if (_locations is EqualUnmodifiableListView) return _locations;
  // ignore: implicit_dynamic_type
  return EqualUnmodifiableListView(_locations);
}

@override final  CounselorSlot? nextAvailable;
/// Profile page only.
@override final  bool? acceptsRequests;
 final  List<CounselorSlot> _upcomingSlots;
@override@JsonKey() List<CounselorSlot> get upcomingSlots {
  if (_upcomingSlots is EqualUnmodifiableListView) return _upcomingSlots;
  // ignore: implicit_dynamic_type
  return EqualUnmodifiableListView(_upcomingSlots);
}


/// Create a copy of Counselor
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$CounselorCopyWith<_Counselor> get copyWith => __$CounselorCopyWithImpl<_Counselor>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$CounselorToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _Counselor&&(identical(other.id, id) || other.id == id)&&(identical(other.name, name) || other.name == name)&&(identical(other.verified, verified) || other.verified == verified)&&const DeepCollectionEquality().equals(other._contexts, _contexts)&&const DeepCollectionEquality().equals(other._specializations, _specializations)&&(identical(other.specializationsCount, specializationsCount) || other.specializationsCount == specializationsCount)&&const DeepCollectionEquality().equals(other._languages, _languages)&&const DeepCollectionEquality().equals(other._sessionModes, _sessionModes)&&const DeepCollectionEquality().equals(other._locations, _locations)&&(identical(other.nextAvailable, nextAvailable) || other.nextAvailable == nextAvailable)&&(identical(other.acceptsRequests, acceptsRequests) || other.acceptsRequests == acceptsRequests)&&const DeepCollectionEquality().equals(other._upcomingSlots, _upcomingSlots));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,name,verified,const DeepCollectionEquality().hash(_contexts),const DeepCollectionEquality().hash(_specializations),specializationsCount,const DeepCollectionEquality().hash(_languages),const DeepCollectionEquality().hash(_sessionModes),const DeepCollectionEquality().hash(_locations),nextAvailable,acceptsRequests,const DeepCollectionEquality().hash(_upcomingSlots));

@override
String toString() {
  return 'Counselor(id: $id, name: $name, verified: $verified, contexts: $contexts, specializations: $specializations, specializationsCount: $specializationsCount, languages: $languages, sessionModes: $sessionModes, locations: $locations, nextAvailable: $nextAvailable, acceptsRequests: $acceptsRequests, upcomingSlots: $upcomingSlots)';
}


}

/// @nodoc
abstract mixin class _$CounselorCopyWith<$Res> implements $CounselorCopyWith<$Res> {
  factory _$CounselorCopyWith(_Counselor value, $Res Function(_Counselor) _then) = __$CounselorCopyWithImpl;
@override @useResult
$Res call({
 String id, String name, bool verified, List<CounselorContext> contexts, List<String> specializations, int specializationsCount, List<String> languages,@JsonKey(unknownEnumValue: SessionMode.unknown) List<SessionMode> sessionModes, List<CounselorLocation> locations, CounselorSlot? nextAvailable, bool? acceptsRequests, List<CounselorSlot> upcomingSlots
});


@override $CounselorSlotCopyWith<$Res>? get nextAvailable;

}
/// @nodoc
class __$CounselorCopyWithImpl<$Res>
    implements _$CounselorCopyWith<$Res> {
  __$CounselorCopyWithImpl(this._self, this._then);

  final _Counselor _self;
  final $Res Function(_Counselor) _then;

/// Create a copy of Counselor
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? id = null,Object? name = null,Object? verified = null,Object? contexts = null,Object? specializations = null,Object? specializationsCount = null,Object? languages = null,Object? sessionModes = null,Object? locations = null,Object? nextAvailable = freezed,Object? acceptsRequests = freezed,Object? upcomingSlots = null,}) {
  return _then(_Counselor(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as String,name: null == name ? _self.name : name // ignore: cast_nullable_to_non_nullable
as String,verified: null == verified ? _self.verified : verified // ignore: cast_nullable_to_non_nullable
as bool,contexts: null == contexts ? _self._contexts : contexts // ignore: cast_nullable_to_non_nullable
as List<CounselorContext>,specializations: null == specializations ? _self._specializations : specializations // ignore: cast_nullable_to_non_nullable
as List<String>,specializationsCount: null == specializationsCount ? _self.specializationsCount : specializationsCount // ignore: cast_nullable_to_non_nullable
as int,languages: null == languages ? _self._languages : languages // ignore: cast_nullable_to_non_nullable
as List<String>,sessionModes: null == sessionModes ? _self._sessionModes : sessionModes // ignore: cast_nullable_to_non_nullable
as List<SessionMode>,locations: null == locations ? _self._locations : locations // ignore: cast_nullable_to_non_nullable
as List<CounselorLocation>,nextAvailable: freezed == nextAvailable ? _self.nextAvailable : nextAvailable // ignore: cast_nullable_to_non_nullable
as CounselorSlot?,acceptsRequests: freezed == acceptsRequests ? _self.acceptsRequests : acceptsRequests // ignore: cast_nullable_to_non_nullable
as bool?,upcomingSlots: null == upcomingSlots ? _self._upcomingSlots : upcomingSlots // ignore: cast_nullable_to_non_nullable
as List<CounselorSlot>,
  ));
}

/// Create a copy of Counselor
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$CounselorSlotCopyWith<$Res>? get nextAvailable {
    if (_self.nextAvailable == null) {
    return null;
  }

  return $CounselorSlotCopyWith<$Res>(_self.nextAvailable!, (value) {
    return _then(_self.copyWith(nextAvailable: value));
  });
}
}

// dart format on
