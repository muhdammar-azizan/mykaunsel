// GENERATED CODE - DO NOT MODIFY BY HAND
// coverage:ignore-file
// ignore_for_file: type=lint
// ignore_for_file: unused_element, deprecated_member_use, deprecated_member_use_from_same_package, use_function_type_syntax_for_parameters, unnecessary_const, avoid_init_to_null, invalid_override_different_default_values_named, prefer_expression_function_bodies, annotate_overrides, invalid_annotation_target, unnecessary_question_mark

part of 'counselor_query.dart';

// **************************************************************************
// FreezedGenerator
// **************************************************************************

// dart format off
T _$identity<T>(T value) => value;
/// @nodoc
mixin _$CounselorQuery {

 String? get q; String? get specialization; String? get language; SessionMode? get mode; String? get state;
/// Create a copy of CounselorQuery
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$CounselorQueryCopyWith<CounselorQuery> get copyWith => _$CounselorQueryCopyWithImpl<CounselorQuery>(this as CounselorQuery, _$identity);



@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is CounselorQuery&&(identical(other.q, q) || other.q == q)&&(identical(other.specialization, specialization) || other.specialization == specialization)&&(identical(other.language, language) || other.language == language)&&(identical(other.mode, mode) || other.mode == mode)&&(identical(other.state, state) || other.state == state));
}


@override
int get hashCode => Object.hash(runtimeType,q,specialization,language,mode,state);

@override
String toString() {
  return 'CounselorQuery(q: $q, specialization: $specialization, language: $language, mode: $mode, state: $state)';
}


}

/// @nodoc
abstract mixin class $CounselorQueryCopyWith<$Res>  {
  factory $CounselorQueryCopyWith(CounselorQuery value, $Res Function(CounselorQuery) _then) = _$CounselorQueryCopyWithImpl;
@useResult
$Res call({
 String? q, String? specialization, String? language, SessionMode? mode, String? state
});




}
/// @nodoc
class _$CounselorQueryCopyWithImpl<$Res>
    implements $CounselorQueryCopyWith<$Res> {
  _$CounselorQueryCopyWithImpl(this._self, this._then);

  final CounselorQuery _self;
  final $Res Function(CounselorQuery) _then;

/// Create a copy of CounselorQuery
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? q = freezed,Object? specialization = freezed,Object? language = freezed,Object? mode = freezed,Object? state = freezed,}) {
  return _then(_self.copyWith(
q: freezed == q ? _self.q : q // ignore: cast_nullable_to_non_nullable
as String?,specialization: freezed == specialization ? _self.specialization : specialization // ignore: cast_nullable_to_non_nullable
as String?,language: freezed == language ? _self.language : language // ignore: cast_nullable_to_non_nullable
as String?,mode: freezed == mode ? _self.mode : mode // ignore: cast_nullable_to_non_nullable
as SessionMode?,state: freezed == state ? _self.state : state // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}

}


/// Adds pattern-matching-related methods to [CounselorQuery].
extension CounselorQueryPatterns on CounselorQuery {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _CounselorQuery value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _CounselorQuery() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _CounselorQuery value)  $default,){
final _that = this;
switch (_that) {
case _CounselorQuery():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _CounselorQuery value)?  $default,){
final _that = this;
switch (_that) {
case _CounselorQuery() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( String? q,  String? specialization,  String? language,  SessionMode? mode,  String? state)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _CounselorQuery() when $default != null:
return $default(_that.q,_that.specialization,_that.language,_that.mode,_that.state);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( String? q,  String? specialization,  String? language,  SessionMode? mode,  String? state)  $default,) {final _that = this;
switch (_that) {
case _CounselorQuery():
return $default(_that.q,_that.specialization,_that.language,_that.mode,_that.state);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( String? q,  String? specialization,  String? language,  SessionMode? mode,  String? state)?  $default,) {final _that = this;
switch (_that) {
case _CounselorQuery() when $default != null:
return $default(_that.q,_that.specialization,_that.language,_that.mode,_that.state);case _:
  return null;

}
}

}

/// @nodoc


class _CounselorQuery extends CounselorQuery {
  const _CounselorQuery({this.q, this.specialization, this.language, this.mode, this.state}): super._();
  

@override final  String? q;
@override final  String? specialization;
@override final  String? language;
@override final  SessionMode? mode;
@override final  String? state;

/// Create a copy of CounselorQuery
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$CounselorQueryCopyWith<_CounselorQuery> get copyWith => __$CounselorQueryCopyWithImpl<_CounselorQuery>(this, _$identity);



@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _CounselorQuery&&(identical(other.q, q) || other.q == q)&&(identical(other.specialization, specialization) || other.specialization == specialization)&&(identical(other.language, language) || other.language == language)&&(identical(other.mode, mode) || other.mode == mode)&&(identical(other.state, state) || other.state == state));
}


@override
int get hashCode => Object.hash(runtimeType,q,specialization,language,mode,state);

@override
String toString() {
  return 'CounselorQuery(q: $q, specialization: $specialization, language: $language, mode: $mode, state: $state)';
}


}

/// @nodoc
abstract mixin class _$CounselorQueryCopyWith<$Res> implements $CounselorQueryCopyWith<$Res> {
  factory _$CounselorQueryCopyWith(_CounselorQuery value, $Res Function(_CounselorQuery) _then) = __$CounselorQueryCopyWithImpl;
@override @useResult
$Res call({
 String? q, String? specialization, String? language, SessionMode? mode, String? state
});




}
/// @nodoc
class __$CounselorQueryCopyWithImpl<$Res>
    implements _$CounselorQueryCopyWith<$Res> {
  __$CounselorQueryCopyWithImpl(this._self, this._then);

  final _CounselorQuery _self;
  final $Res Function(_CounselorQuery) _then;

/// Create a copy of CounselorQuery
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? q = freezed,Object? specialization = freezed,Object? language = freezed,Object? mode = freezed,Object? state = freezed,}) {
  return _then(_CounselorQuery(
q: freezed == q ? _self.q : q // ignore: cast_nullable_to_non_nullable
as String?,specialization: freezed == specialization ? _self.specialization : specialization // ignore: cast_nullable_to_non_nullable
as String?,language: freezed == language ? _self.language : language // ignore: cast_nullable_to_non_nullable
as String?,mode: freezed == mode ? _self.mode : mode // ignore: cast_nullable_to_non_nullable
as SessionMode?,state: freezed == state ? _self.state : state // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}


}


/// @nodoc
mixin _$CounselorFilterOptions {

 List<String> get specializations; List<String> get languages;@JsonKey(unknownEnumValue: SessionMode.unknown) List<SessionMode> get sessionModes; List<String> get states;
/// Create a copy of CounselorFilterOptions
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$CounselorFilterOptionsCopyWith<CounselorFilterOptions> get copyWith => _$CounselorFilterOptionsCopyWithImpl<CounselorFilterOptions>(this as CounselorFilterOptions, _$identity);

  /// Serializes this CounselorFilterOptions to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is CounselorFilterOptions&&const DeepCollectionEquality().equals(other.specializations, specializations)&&const DeepCollectionEquality().equals(other.languages, languages)&&const DeepCollectionEquality().equals(other.sessionModes, sessionModes)&&const DeepCollectionEquality().equals(other.states, states));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,const DeepCollectionEquality().hash(specializations),const DeepCollectionEquality().hash(languages),const DeepCollectionEquality().hash(sessionModes),const DeepCollectionEquality().hash(states));

@override
String toString() {
  return 'CounselorFilterOptions(specializations: $specializations, languages: $languages, sessionModes: $sessionModes, states: $states)';
}


}

/// @nodoc
abstract mixin class $CounselorFilterOptionsCopyWith<$Res>  {
  factory $CounselorFilterOptionsCopyWith(CounselorFilterOptions value, $Res Function(CounselorFilterOptions) _then) = _$CounselorFilterOptionsCopyWithImpl;
@useResult
$Res call({
 List<String> specializations, List<String> languages,@JsonKey(unknownEnumValue: SessionMode.unknown) List<SessionMode> sessionModes, List<String> states
});




}
/// @nodoc
class _$CounselorFilterOptionsCopyWithImpl<$Res>
    implements $CounselorFilterOptionsCopyWith<$Res> {
  _$CounselorFilterOptionsCopyWithImpl(this._self, this._then);

  final CounselorFilterOptions _self;
  final $Res Function(CounselorFilterOptions) _then;

/// Create a copy of CounselorFilterOptions
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? specializations = null,Object? languages = null,Object? sessionModes = null,Object? states = null,}) {
  return _then(_self.copyWith(
specializations: null == specializations ? _self.specializations : specializations // ignore: cast_nullable_to_non_nullable
as List<String>,languages: null == languages ? _self.languages : languages // ignore: cast_nullable_to_non_nullable
as List<String>,sessionModes: null == sessionModes ? _self.sessionModes : sessionModes // ignore: cast_nullable_to_non_nullable
as List<SessionMode>,states: null == states ? _self.states : states // ignore: cast_nullable_to_non_nullable
as List<String>,
  ));
}

}


/// Adds pattern-matching-related methods to [CounselorFilterOptions].
extension CounselorFilterOptionsPatterns on CounselorFilterOptions {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _CounselorFilterOptions value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _CounselorFilterOptions() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _CounselorFilterOptions value)  $default,){
final _that = this;
switch (_that) {
case _CounselorFilterOptions():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _CounselorFilterOptions value)?  $default,){
final _that = this;
switch (_that) {
case _CounselorFilterOptions() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( List<String> specializations,  List<String> languages, @JsonKey(unknownEnumValue: SessionMode.unknown)  List<SessionMode> sessionModes,  List<String> states)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _CounselorFilterOptions() when $default != null:
return $default(_that.specializations,_that.languages,_that.sessionModes,_that.states);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( List<String> specializations,  List<String> languages, @JsonKey(unknownEnumValue: SessionMode.unknown)  List<SessionMode> sessionModes,  List<String> states)  $default,) {final _that = this;
switch (_that) {
case _CounselorFilterOptions():
return $default(_that.specializations,_that.languages,_that.sessionModes,_that.states);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( List<String> specializations,  List<String> languages, @JsonKey(unknownEnumValue: SessionMode.unknown)  List<SessionMode> sessionModes,  List<String> states)?  $default,) {final _that = this;
switch (_that) {
case _CounselorFilterOptions() when $default != null:
return $default(_that.specializations,_that.languages,_that.sessionModes,_that.states);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _CounselorFilterOptions implements CounselorFilterOptions {
  const _CounselorFilterOptions({final  List<String> specializations = const [], final  List<String> languages = const [], @JsonKey(unknownEnumValue: SessionMode.unknown) final  List<SessionMode> sessionModes = const [], final  List<String> states = const []}): _specializations = specializations,_languages = languages,_sessionModes = sessionModes,_states = states;
  factory _CounselorFilterOptions.fromJson(Map<String, dynamic> json) => _$CounselorFilterOptionsFromJson(json);

 final  List<String> _specializations;
@override@JsonKey() List<String> get specializations {
  if (_specializations is EqualUnmodifiableListView) return _specializations;
  // ignore: implicit_dynamic_type
  return EqualUnmodifiableListView(_specializations);
}

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

 final  List<String> _states;
@override@JsonKey() List<String> get states {
  if (_states is EqualUnmodifiableListView) return _states;
  // ignore: implicit_dynamic_type
  return EqualUnmodifiableListView(_states);
}


/// Create a copy of CounselorFilterOptions
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$CounselorFilterOptionsCopyWith<_CounselorFilterOptions> get copyWith => __$CounselorFilterOptionsCopyWithImpl<_CounselorFilterOptions>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$CounselorFilterOptionsToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _CounselorFilterOptions&&const DeepCollectionEquality().equals(other._specializations, _specializations)&&const DeepCollectionEquality().equals(other._languages, _languages)&&const DeepCollectionEquality().equals(other._sessionModes, _sessionModes)&&const DeepCollectionEquality().equals(other._states, _states));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,const DeepCollectionEquality().hash(_specializations),const DeepCollectionEquality().hash(_languages),const DeepCollectionEquality().hash(_sessionModes),const DeepCollectionEquality().hash(_states));

@override
String toString() {
  return 'CounselorFilterOptions(specializations: $specializations, languages: $languages, sessionModes: $sessionModes, states: $states)';
}


}

/// @nodoc
abstract mixin class _$CounselorFilterOptionsCopyWith<$Res> implements $CounselorFilterOptionsCopyWith<$Res> {
  factory _$CounselorFilterOptionsCopyWith(_CounselorFilterOptions value, $Res Function(_CounselorFilterOptions) _then) = __$CounselorFilterOptionsCopyWithImpl;
@override @useResult
$Res call({
 List<String> specializations, List<String> languages,@JsonKey(unknownEnumValue: SessionMode.unknown) List<SessionMode> sessionModes, List<String> states
});




}
/// @nodoc
class __$CounselorFilterOptionsCopyWithImpl<$Res>
    implements _$CounselorFilterOptionsCopyWith<$Res> {
  __$CounselorFilterOptionsCopyWithImpl(this._self, this._then);

  final _CounselorFilterOptions _self;
  final $Res Function(_CounselorFilterOptions) _then;

/// Create a copy of CounselorFilterOptions
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? specializations = null,Object? languages = null,Object? sessionModes = null,Object? states = null,}) {
  return _then(_CounselorFilterOptions(
specializations: null == specializations ? _self._specializations : specializations // ignore: cast_nullable_to_non_nullable
as List<String>,languages: null == languages ? _self._languages : languages // ignore: cast_nullable_to_non_nullable
as List<String>,sessionModes: null == sessionModes ? _self._sessionModes : sessionModes // ignore: cast_nullable_to_non_nullable
as List<SessionMode>,states: null == states ? _self._states : states // ignore: cast_nullable_to_non_nullable
as List<String>,
  ));
}


}

// dart format on
