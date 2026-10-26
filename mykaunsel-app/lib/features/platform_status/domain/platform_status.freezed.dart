// GENERATED CODE - DO NOT MODIFY BY HAND
// coverage:ignore-file
// ignore_for_file: type=lint
// ignore_for_file: unused_element, deprecated_member_use, deprecated_member_use_from_same_package, use_function_type_syntax_for_parameters, unnecessary_const, avoid_init_to_null, invalid_override_different_default_values_named, prefer_expression_function_bodies, annotate_overrides, invalid_annotation_target, unnecessary_question_mark

part of 'platform_status.dart';

// **************************************************************************
// FreezedGenerator
// **************************************************************************

// dart format off
T _$identity<T>(T value) => value;

/// @nodoc
mixin _$PlatformStatus {

 MaintenanceStatus get maintenance; AppVersionStatus get app; Map<String, RegistrationStatus> get registration;
/// Create a copy of PlatformStatus
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$PlatformStatusCopyWith<PlatformStatus> get copyWith => _$PlatformStatusCopyWithImpl<PlatformStatus>(this as PlatformStatus, _$identity);

  /// Serializes this PlatformStatus to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is PlatformStatus&&(identical(other.maintenance, maintenance) || other.maintenance == maintenance)&&(identical(other.app, app) || other.app == app)&&const DeepCollectionEquality().equals(other.registration, registration));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,maintenance,app,const DeepCollectionEquality().hash(registration));

@override
String toString() {
  return 'PlatformStatus(maintenance: $maintenance, app: $app, registration: $registration)';
}


}

/// @nodoc
abstract mixin class $PlatformStatusCopyWith<$Res>  {
  factory $PlatformStatusCopyWith(PlatformStatus value, $Res Function(PlatformStatus) _then) = _$PlatformStatusCopyWithImpl;
@useResult
$Res call({
 MaintenanceStatus maintenance, AppVersionStatus app, Map<String, RegistrationStatus> registration
});


$MaintenanceStatusCopyWith<$Res> get maintenance;$AppVersionStatusCopyWith<$Res> get app;

}
/// @nodoc
class _$PlatformStatusCopyWithImpl<$Res>
    implements $PlatformStatusCopyWith<$Res> {
  _$PlatformStatusCopyWithImpl(this._self, this._then);

  final PlatformStatus _self;
  final $Res Function(PlatformStatus) _then;

/// Create a copy of PlatformStatus
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? maintenance = null,Object? app = null,Object? registration = null,}) {
  return _then(_self.copyWith(
maintenance: null == maintenance ? _self.maintenance : maintenance // ignore: cast_nullable_to_non_nullable
as MaintenanceStatus,app: null == app ? _self.app : app // ignore: cast_nullable_to_non_nullable
as AppVersionStatus,registration: null == registration ? _self.registration : registration // ignore: cast_nullable_to_non_nullable
as Map<String, RegistrationStatus>,
  ));
}
/// Create a copy of PlatformStatus
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$MaintenanceStatusCopyWith<$Res> get maintenance {
  
  return $MaintenanceStatusCopyWith<$Res>(_self.maintenance, (value) {
    return _then(_self.copyWith(maintenance: value));
  });
}/// Create a copy of PlatformStatus
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$AppVersionStatusCopyWith<$Res> get app {
  
  return $AppVersionStatusCopyWith<$Res>(_self.app, (value) {
    return _then(_self.copyWith(app: value));
  });
}
}


/// Adds pattern-matching-related methods to [PlatformStatus].
extension PlatformStatusPatterns on PlatformStatus {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _PlatformStatus value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _PlatformStatus() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _PlatformStatus value)  $default,){
final _that = this;
switch (_that) {
case _PlatformStatus():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _PlatformStatus value)?  $default,){
final _that = this;
switch (_that) {
case _PlatformStatus() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( MaintenanceStatus maintenance,  AppVersionStatus app,  Map<String, RegistrationStatus> registration)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _PlatformStatus() when $default != null:
return $default(_that.maintenance,_that.app,_that.registration);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( MaintenanceStatus maintenance,  AppVersionStatus app,  Map<String, RegistrationStatus> registration)  $default,) {final _that = this;
switch (_that) {
case _PlatformStatus():
return $default(_that.maintenance,_that.app,_that.registration);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( MaintenanceStatus maintenance,  AppVersionStatus app,  Map<String, RegistrationStatus> registration)?  $default,) {final _that = this;
switch (_that) {
case _PlatformStatus() when $default != null:
return $default(_that.maintenance,_that.app,_that.registration);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _PlatformStatus implements PlatformStatus {
  const _PlatformStatus({required this.maintenance, required this.app, final  Map<String, RegistrationStatus> registration = const {}}): _registration = registration;
  factory _PlatformStatus.fromJson(Map<String, dynamic> json) => _$PlatformStatusFromJson(json);

@override final  MaintenanceStatus maintenance;
@override final  AppVersionStatus app;
 final  Map<String, RegistrationStatus> _registration;
@override@JsonKey() Map<String, RegistrationStatus> get registration {
  if (_registration is EqualUnmodifiableMapView) return _registration;
  // ignore: implicit_dynamic_type
  return EqualUnmodifiableMapView(_registration);
}


/// Create a copy of PlatformStatus
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$PlatformStatusCopyWith<_PlatformStatus> get copyWith => __$PlatformStatusCopyWithImpl<_PlatformStatus>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$PlatformStatusToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _PlatformStatus&&(identical(other.maintenance, maintenance) || other.maintenance == maintenance)&&(identical(other.app, app) || other.app == app)&&const DeepCollectionEquality().equals(other._registration, _registration));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,maintenance,app,const DeepCollectionEquality().hash(_registration));

@override
String toString() {
  return 'PlatformStatus(maintenance: $maintenance, app: $app, registration: $registration)';
}


}

/// @nodoc
abstract mixin class _$PlatformStatusCopyWith<$Res> implements $PlatformStatusCopyWith<$Res> {
  factory _$PlatformStatusCopyWith(_PlatformStatus value, $Res Function(_PlatformStatus) _then) = __$PlatformStatusCopyWithImpl;
@override @useResult
$Res call({
 MaintenanceStatus maintenance, AppVersionStatus app, Map<String, RegistrationStatus> registration
});


@override $MaintenanceStatusCopyWith<$Res> get maintenance;@override $AppVersionStatusCopyWith<$Res> get app;

}
/// @nodoc
class __$PlatformStatusCopyWithImpl<$Res>
    implements _$PlatformStatusCopyWith<$Res> {
  __$PlatformStatusCopyWithImpl(this._self, this._then);

  final _PlatformStatus _self;
  final $Res Function(_PlatformStatus) _then;

/// Create a copy of PlatformStatus
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? maintenance = null,Object? app = null,Object? registration = null,}) {
  return _then(_PlatformStatus(
maintenance: null == maintenance ? _self.maintenance : maintenance // ignore: cast_nullable_to_non_nullable
as MaintenanceStatus,app: null == app ? _self.app : app // ignore: cast_nullable_to_non_nullable
as AppVersionStatus,registration: null == registration ? _self._registration : registration // ignore: cast_nullable_to_non_nullable
as Map<String, RegistrationStatus>,
  ));
}

/// Create a copy of PlatformStatus
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$MaintenanceStatusCopyWith<$Res> get maintenance {
  
  return $MaintenanceStatusCopyWith<$Res>(_self.maintenance, (value) {
    return _then(_self.copyWith(maintenance: value));
  });
}/// Create a copy of PlatformStatus
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$AppVersionStatusCopyWith<$Res> get app {
  
  return $AppVersionStatusCopyWith<$Res>(_self.app, (value) {
    return _then(_self.copyWith(app: value));
  });
}
}


/// @nodoc
mixin _$MaintenanceStatus {

@JsonKey(name: 'on') bool get isOn; String? get message;
/// Create a copy of MaintenanceStatus
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$MaintenanceStatusCopyWith<MaintenanceStatus> get copyWith => _$MaintenanceStatusCopyWithImpl<MaintenanceStatus>(this as MaintenanceStatus, _$identity);

  /// Serializes this MaintenanceStatus to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is MaintenanceStatus&&(identical(other.isOn, isOn) || other.isOn == isOn)&&(identical(other.message, message) || other.message == message));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,isOn,message);

@override
String toString() {
  return 'MaintenanceStatus(isOn: $isOn, message: $message)';
}


}

/// @nodoc
abstract mixin class $MaintenanceStatusCopyWith<$Res>  {
  factory $MaintenanceStatusCopyWith(MaintenanceStatus value, $Res Function(MaintenanceStatus) _then) = _$MaintenanceStatusCopyWithImpl;
@useResult
$Res call({
@JsonKey(name: 'on') bool isOn, String? message
});




}
/// @nodoc
class _$MaintenanceStatusCopyWithImpl<$Res>
    implements $MaintenanceStatusCopyWith<$Res> {
  _$MaintenanceStatusCopyWithImpl(this._self, this._then);

  final MaintenanceStatus _self;
  final $Res Function(MaintenanceStatus) _then;

/// Create a copy of MaintenanceStatus
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? isOn = null,Object? message = freezed,}) {
  return _then(_self.copyWith(
isOn: null == isOn ? _self.isOn : isOn // ignore: cast_nullable_to_non_nullable
as bool,message: freezed == message ? _self.message : message // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}

}


/// Adds pattern-matching-related methods to [MaintenanceStatus].
extension MaintenanceStatusPatterns on MaintenanceStatus {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _MaintenanceStatus value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _MaintenanceStatus() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _MaintenanceStatus value)  $default,){
final _that = this;
switch (_that) {
case _MaintenanceStatus():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _MaintenanceStatus value)?  $default,){
final _that = this;
switch (_that) {
case _MaintenanceStatus() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function(@JsonKey(name: 'on')  bool isOn,  String? message)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _MaintenanceStatus() when $default != null:
return $default(_that.isOn,_that.message);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function(@JsonKey(name: 'on')  bool isOn,  String? message)  $default,) {final _that = this;
switch (_that) {
case _MaintenanceStatus():
return $default(_that.isOn,_that.message);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function(@JsonKey(name: 'on')  bool isOn,  String? message)?  $default,) {final _that = this;
switch (_that) {
case _MaintenanceStatus() when $default != null:
return $default(_that.isOn,_that.message);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _MaintenanceStatus implements MaintenanceStatus {
  const _MaintenanceStatus({@JsonKey(name: 'on') required this.isOn, this.message});
  factory _MaintenanceStatus.fromJson(Map<String, dynamic> json) => _$MaintenanceStatusFromJson(json);

@override@JsonKey(name: 'on') final  bool isOn;
@override final  String? message;

/// Create a copy of MaintenanceStatus
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$MaintenanceStatusCopyWith<_MaintenanceStatus> get copyWith => __$MaintenanceStatusCopyWithImpl<_MaintenanceStatus>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$MaintenanceStatusToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _MaintenanceStatus&&(identical(other.isOn, isOn) || other.isOn == isOn)&&(identical(other.message, message) || other.message == message));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,isOn,message);

@override
String toString() {
  return 'MaintenanceStatus(isOn: $isOn, message: $message)';
}


}

/// @nodoc
abstract mixin class _$MaintenanceStatusCopyWith<$Res> implements $MaintenanceStatusCopyWith<$Res> {
  factory _$MaintenanceStatusCopyWith(_MaintenanceStatus value, $Res Function(_MaintenanceStatus) _then) = __$MaintenanceStatusCopyWithImpl;
@override @useResult
$Res call({
@JsonKey(name: 'on') bool isOn, String? message
});




}
/// @nodoc
class __$MaintenanceStatusCopyWithImpl<$Res>
    implements _$MaintenanceStatusCopyWith<$Res> {
  __$MaintenanceStatusCopyWithImpl(this._self, this._then);

  final _MaintenanceStatus _self;
  final $Res Function(_MaintenanceStatus) _then;

/// Create a copy of MaintenanceStatus
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? isOn = null,Object? message = freezed,}) {
  return _then(_MaintenanceStatus(
isOn: null == isOn ? _self.isOn : isOn // ignore: cast_nullable_to_non_nullable
as bool,message: freezed == message ? _self.message : message // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}


}


/// @nodoc
mixin _$AppVersionStatus {

 bool get updateRequired; String? get minVersion; String? get message; String? get storeUrl;
/// Create a copy of AppVersionStatus
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$AppVersionStatusCopyWith<AppVersionStatus> get copyWith => _$AppVersionStatusCopyWithImpl<AppVersionStatus>(this as AppVersionStatus, _$identity);

  /// Serializes this AppVersionStatus to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is AppVersionStatus&&(identical(other.updateRequired, updateRequired) || other.updateRequired == updateRequired)&&(identical(other.minVersion, minVersion) || other.minVersion == minVersion)&&(identical(other.message, message) || other.message == message)&&(identical(other.storeUrl, storeUrl) || other.storeUrl == storeUrl));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,updateRequired,minVersion,message,storeUrl);

@override
String toString() {
  return 'AppVersionStatus(updateRequired: $updateRequired, minVersion: $minVersion, message: $message, storeUrl: $storeUrl)';
}


}

/// @nodoc
abstract mixin class $AppVersionStatusCopyWith<$Res>  {
  factory $AppVersionStatusCopyWith(AppVersionStatus value, $Res Function(AppVersionStatus) _then) = _$AppVersionStatusCopyWithImpl;
@useResult
$Res call({
 bool updateRequired, String? minVersion, String? message, String? storeUrl
});




}
/// @nodoc
class _$AppVersionStatusCopyWithImpl<$Res>
    implements $AppVersionStatusCopyWith<$Res> {
  _$AppVersionStatusCopyWithImpl(this._self, this._then);

  final AppVersionStatus _self;
  final $Res Function(AppVersionStatus) _then;

/// Create a copy of AppVersionStatus
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? updateRequired = null,Object? minVersion = freezed,Object? message = freezed,Object? storeUrl = freezed,}) {
  return _then(_self.copyWith(
updateRequired: null == updateRequired ? _self.updateRequired : updateRequired // ignore: cast_nullable_to_non_nullable
as bool,minVersion: freezed == minVersion ? _self.minVersion : minVersion // ignore: cast_nullable_to_non_nullable
as String?,message: freezed == message ? _self.message : message // ignore: cast_nullable_to_non_nullable
as String?,storeUrl: freezed == storeUrl ? _self.storeUrl : storeUrl // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}

}


/// Adds pattern-matching-related methods to [AppVersionStatus].
extension AppVersionStatusPatterns on AppVersionStatus {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _AppVersionStatus value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _AppVersionStatus() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _AppVersionStatus value)  $default,){
final _that = this;
switch (_that) {
case _AppVersionStatus():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _AppVersionStatus value)?  $default,){
final _that = this;
switch (_that) {
case _AppVersionStatus() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( bool updateRequired,  String? minVersion,  String? message,  String? storeUrl)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _AppVersionStatus() when $default != null:
return $default(_that.updateRequired,_that.minVersion,_that.message,_that.storeUrl);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( bool updateRequired,  String? minVersion,  String? message,  String? storeUrl)  $default,) {final _that = this;
switch (_that) {
case _AppVersionStatus():
return $default(_that.updateRequired,_that.minVersion,_that.message,_that.storeUrl);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( bool updateRequired,  String? minVersion,  String? message,  String? storeUrl)?  $default,) {final _that = this;
switch (_that) {
case _AppVersionStatus() when $default != null:
return $default(_that.updateRequired,_that.minVersion,_that.message,_that.storeUrl);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _AppVersionStatus implements AppVersionStatus {
  const _AppVersionStatus({required this.updateRequired, this.minVersion, this.message, this.storeUrl});
  factory _AppVersionStatus.fromJson(Map<String, dynamic> json) => _$AppVersionStatusFromJson(json);

@override final  bool updateRequired;
@override final  String? minVersion;
@override final  String? message;
@override final  String? storeUrl;

/// Create a copy of AppVersionStatus
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$AppVersionStatusCopyWith<_AppVersionStatus> get copyWith => __$AppVersionStatusCopyWithImpl<_AppVersionStatus>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$AppVersionStatusToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _AppVersionStatus&&(identical(other.updateRequired, updateRequired) || other.updateRequired == updateRequired)&&(identical(other.minVersion, minVersion) || other.minVersion == minVersion)&&(identical(other.message, message) || other.message == message)&&(identical(other.storeUrl, storeUrl) || other.storeUrl == storeUrl));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,updateRequired,minVersion,message,storeUrl);

@override
String toString() {
  return 'AppVersionStatus(updateRequired: $updateRequired, minVersion: $minVersion, message: $message, storeUrl: $storeUrl)';
}


}

/// @nodoc
abstract mixin class _$AppVersionStatusCopyWith<$Res> implements $AppVersionStatusCopyWith<$Res> {
  factory _$AppVersionStatusCopyWith(_AppVersionStatus value, $Res Function(_AppVersionStatus) _then) = __$AppVersionStatusCopyWithImpl;
@override @useResult
$Res call({
 bool updateRequired, String? minVersion, String? message, String? storeUrl
});




}
/// @nodoc
class __$AppVersionStatusCopyWithImpl<$Res>
    implements _$AppVersionStatusCopyWith<$Res> {
  __$AppVersionStatusCopyWithImpl(this._self, this._then);

  final _AppVersionStatus _self;
  final $Res Function(_AppVersionStatus) _then;

/// Create a copy of AppVersionStatus
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? updateRequired = null,Object? minVersion = freezed,Object? message = freezed,Object? storeUrl = freezed,}) {
  return _then(_AppVersionStatus(
updateRequired: null == updateRequired ? _self.updateRequired : updateRequired // ignore: cast_nullable_to_non_nullable
as bool,minVersion: freezed == minVersion ? _self.minVersion : minVersion // ignore: cast_nullable_to_non_nullable
as String?,message: freezed == message ? _self.message : message // ignore: cast_nullable_to_non_nullable
as String?,storeUrl: freezed == storeUrl ? _self.storeUrl : storeUrl // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}


}


/// @nodoc
mixin _$RegistrationStatus {

 bool get open; String? get message;
/// Create a copy of RegistrationStatus
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$RegistrationStatusCopyWith<RegistrationStatus> get copyWith => _$RegistrationStatusCopyWithImpl<RegistrationStatus>(this as RegistrationStatus, _$identity);

  /// Serializes this RegistrationStatus to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is RegistrationStatus&&(identical(other.open, open) || other.open == open)&&(identical(other.message, message) || other.message == message));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,open,message);

@override
String toString() {
  return 'RegistrationStatus(open: $open, message: $message)';
}


}

/// @nodoc
abstract mixin class $RegistrationStatusCopyWith<$Res>  {
  factory $RegistrationStatusCopyWith(RegistrationStatus value, $Res Function(RegistrationStatus) _then) = _$RegistrationStatusCopyWithImpl;
@useResult
$Res call({
 bool open, String? message
});




}
/// @nodoc
class _$RegistrationStatusCopyWithImpl<$Res>
    implements $RegistrationStatusCopyWith<$Res> {
  _$RegistrationStatusCopyWithImpl(this._self, this._then);

  final RegistrationStatus _self;
  final $Res Function(RegistrationStatus) _then;

/// Create a copy of RegistrationStatus
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? open = null,Object? message = freezed,}) {
  return _then(_self.copyWith(
open: null == open ? _self.open : open // ignore: cast_nullable_to_non_nullable
as bool,message: freezed == message ? _self.message : message // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}

}


/// Adds pattern-matching-related methods to [RegistrationStatus].
extension RegistrationStatusPatterns on RegistrationStatus {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _RegistrationStatus value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _RegistrationStatus() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _RegistrationStatus value)  $default,){
final _that = this;
switch (_that) {
case _RegistrationStatus():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _RegistrationStatus value)?  $default,){
final _that = this;
switch (_that) {
case _RegistrationStatus() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( bool open,  String? message)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _RegistrationStatus() when $default != null:
return $default(_that.open,_that.message);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( bool open,  String? message)  $default,) {final _that = this;
switch (_that) {
case _RegistrationStatus():
return $default(_that.open,_that.message);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( bool open,  String? message)?  $default,) {final _that = this;
switch (_that) {
case _RegistrationStatus() when $default != null:
return $default(_that.open,_that.message);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _RegistrationStatus implements RegistrationStatus {
  const _RegistrationStatus({required this.open, this.message});
  factory _RegistrationStatus.fromJson(Map<String, dynamic> json) => _$RegistrationStatusFromJson(json);

@override final  bool open;
@override final  String? message;

/// Create a copy of RegistrationStatus
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$RegistrationStatusCopyWith<_RegistrationStatus> get copyWith => __$RegistrationStatusCopyWithImpl<_RegistrationStatus>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$RegistrationStatusToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _RegistrationStatus&&(identical(other.open, open) || other.open == open)&&(identical(other.message, message) || other.message == message));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,open,message);

@override
String toString() {
  return 'RegistrationStatus(open: $open, message: $message)';
}


}

/// @nodoc
abstract mixin class _$RegistrationStatusCopyWith<$Res> implements $RegistrationStatusCopyWith<$Res> {
  factory _$RegistrationStatusCopyWith(_RegistrationStatus value, $Res Function(_RegistrationStatus) _then) = __$RegistrationStatusCopyWithImpl;
@override @useResult
$Res call({
 bool open, String? message
});




}
/// @nodoc
class __$RegistrationStatusCopyWithImpl<$Res>
    implements _$RegistrationStatusCopyWith<$Res> {
  __$RegistrationStatusCopyWithImpl(this._self, this._then);

  final _RegistrationStatus _self;
  final $Res Function(_RegistrationStatus) _then;

/// Create a copy of RegistrationStatus
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? open = null,Object? message = freezed,}) {
  return _then(_RegistrationStatus(
open: null == open ? _self.open : open // ignore: cast_nullable_to_non_nullable
as bool,message: freezed == message ? _self.message : message // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}


}

// dart format on
