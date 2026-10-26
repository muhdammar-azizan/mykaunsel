// GENERATED CODE - DO NOT MODIFY BY HAND
// coverage:ignore-file
// ignore_for_file: type=lint
// ignore_for_file: unused_element, deprecated_member_use, deprecated_member_use_from_same_package, use_function_type_syntax_for_parameters, unnecessary_const, avoid_init_to_null, invalid_override_different_default_values_named, prefer_expression_function_bodies, annotate_overrides, invalid_annotation_target, unnecessary_question_mark

part of 'signed_in_device.dart';

// **************************************************************************
// FreezedGenerator
// **************************************************************************

// dart format off
T _$identity<T>(T value) => value;

/// @nodoc
mixin _$SignedInDevice {

 int get id; String get name; bool get current; DateTime? get lastUsedAt; DateTime? get createdAt;
/// Create a copy of SignedInDevice
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$SignedInDeviceCopyWith<SignedInDevice> get copyWith => _$SignedInDeviceCopyWithImpl<SignedInDevice>(this as SignedInDevice, _$identity);

  /// Serializes this SignedInDevice to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is SignedInDevice&&(identical(other.id, id) || other.id == id)&&(identical(other.name, name) || other.name == name)&&(identical(other.current, current) || other.current == current)&&(identical(other.lastUsedAt, lastUsedAt) || other.lastUsedAt == lastUsedAt)&&(identical(other.createdAt, createdAt) || other.createdAt == createdAt));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,name,current,lastUsedAt,createdAt);

@override
String toString() {
  return 'SignedInDevice(id: $id, name: $name, current: $current, lastUsedAt: $lastUsedAt, createdAt: $createdAt)';
}


}

/// @nodoc
abstract mixin class $SignedInDeviceCopyWith<$Res>  {
  factory $SignedInDeviceCopyWith(SignedInDevice value, $Res Function(SignedInDevice) _then) = _$SignedInDeviceCopyWithImpl;
@useResult
$Res call({
 int id, String name, bool current, DateTime? lastUsedAt, DateTime? createdAt
});




}
/// @nodoc
class _$SignedInDeviceCopyWithImpl<$Res>
    implements $SignedInDeviceCopyWith<$Res> {
  _$SignedInDeviceCopyWithImpl(this._self, this._then);

  final SignedInDevice _self;
  final $Res Function(SignedInDevice) _then;

/// Create a copy of SignedInDevice
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? id = null,Object? name = null,Object? current = null,Object? lastUsedAt = freezed,Object? createdAt = freezed,}) {
  return _then(_self.copyWith(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,name: null == name ? _self.name : name // ignore: cast_nullable_to_non_nullable
as String,current: null == current ? _self.current : current // ignore: cast_nullable_to_non_nullable
as bool,lastUsedAt: freezed == lastUsedAt ? _self.lastUsedAt : lastUsedAt // ignore: cast_nullable_to_non_nullable
as DateTime?,createdAt: freezed == createdAt ? _self.createdAt : createdAt // ignore: cast_nullable_to_non_nullable
as DateTime?,
  ));
}

}


/// Adds pattern-matching-related methods to [SignedInDevice].
extension SignedInDevicePatterns on SignedInDevice {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _SignedInDevice value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _SignedInDevice() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _SignedInDevice value)  $default,){
final _that = this;
switch (_that) {
case _SignedInDevice():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _SignedInDevice value)?  $default,){
final _that = this;
switch (_that) {
case _SignedInDevice() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( int id,  String name,  bool current,  DateTime? lastUsedAt,  DateTime? createdAt)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _SignedInDevice() when $default != null:
return $default(_that.id,_that.name,_that.current,_that.lastUsedAt,_that.createdAt);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( int id,  String name,  bool current,  DateTime? lastUsedAt,  DateTime? createdAt)  $default,) {final _that = this;
switch (_that) {
case _SignedInDevice():
return $default(_that.id,_that.name,_that.current,_that.lastUsedAt,_that.createdAt);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( int id,  String name,  bool current,  DateTime? lastUsedAt,  DateTime? createdAt)?  $default,) {final _that = this;
switch (_that) {
case _SignedInDevice() when $default != null:
return $default(_that.id,_that.name,_that.current,_that.lastUsedAt,_that.createdAt);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _SignedInDevice implements SignedInDevice {
  const _SignedInDevice({required this.id, required this.name, this.current = false, this.lastUsedAt, this.createdAt});
  factory _SignedInDevice.fromJson(Map<String, dynamic> json) => _$SignedInDeviceFromJson(json);

@override final  int id;
@override final  String name;
@override@JsonKey() final  bool current;
@override final  DateTime? lastUsedAt;
@override final  DateTime? createdAt;

/// Create a copy of SignedInDevice
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$SignedInDeviceCopyWith<_SignedInDevice> get copyWith => __$SignedInDeviceCopyWithImpl<_SignedInDevice>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$SignedInDeviceToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _SignedInDevice&&(identical(other.id, id) || other.id == id)&&(identical(other.name, name) || other.name == name)&&(identical(other.current, current) || other.current == current)&&(identical(other.lastUsedAt, lastUsedAt) || other.lastUsedAt == lastUsedAt)&&(identical(other.createdAt, createdAt) || other.createdAt == createdAt));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,name,current,lastUsedAt,createdAt);

@override
String toString() {
  return 'SignedInDevice(id: $id, name: $name, current: $current, lastUsedAt: $lastUsedAt, createdAt: $createdAt)';
}


}

/// @nodoc
abstract mixin class _$SignedInDeviceCopyWith<$Res> implements $SignedInDeviceCopyWith<$Res> {
  factory _$SignedInDeviceCopyWith(_SignedInDevice value, $Res Function(_SignedInDevice) _then) = __$SignedInDeviceCopyWithImpl;
@override @useResult
$Res call({
 int id, String name, bool current, DateTime? lastUsedAt, DateTime? createdAt
});




}
/// @nodoc
class __$SignedInDeviceCopyWithImpl<$Res>
    implements _$SignedInDeviceCopyWith<$Res> {
  __$SignedInDeviceCopyWithImpl(this._self, this._then);

  final _SignedInDevice _self;
  final $Res Function(_SignedInDevice) _then;

/// Create a copy of SignedInDevice
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? id = null,Object? name = null,Object? current = null,Object? lastUsedAt = freezed,Object? createdAt = freezed,}) {
  return _then(_SignedInDevice(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,name: null == name ? _self.name : name // ignore: cast_nullable_to_non_nullable
as String,current: null == current ? _self.current : current // ignore: cast_nullable_to_non_nullable
as bool,lastUsedAt: freezed == lastUsedAt ? _self.lastUsedAt : lastUsedAt // ignore: cast_nullable_to_non_nullable
as DateTime?,createdAt: freezed == createdAt ? _self.createdAt : createdAt // ignore: cast_nullable_to_non_nullable
as DateTime?,
  ));
}


}

// dart format on
