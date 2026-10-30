// GENERATED CODE - DO NOT MODIFY BY HAND
// coverage:ignore-file
// ignore_for_file: type=lint
// ignore_for_file: unused_element, deprecated_member_use, deprecated_member_use_from_same_package, use_function_type_syntax_for_parameters, unnecessary_const, avoid_init_to_null, invalid_override_different_default_values_named, prefer_expression_function_bodies, annotate_overrides, invalid_annotation_target, unnecessary_question_mark

part of 'chat.dart';

// **************************************************************************
// FreezedGenerator
// **************************************************************************

// dart format off
T _$identity<T>(T value) => value;

/// @nodoc
mixin _$ChatMessage {

 String get id;@JsonKey(unknownEnumValue: ChatSender.unknown) ChatSender get sender; String get text; DateTime get sentAt; bool get crisis;
/// Create a copy of ChatMessage
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$ChatMessageCopyWith<ChatMessage> get copyWith => _$ChatMessageCopyWithImpl<ChatMessage>(this as ChatMessage, _$identity);

  /// Serializes this ChatMessage to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is ChatMessage&&(identical(other.id, id) || other.id == id)&&(identical(other.sender, sender) || other.sender == sender)&&(identical(other.text, text) || other.text == text)&&(identical(other.sentAt, sentAt) || other.sentAt == sentAt)&&(identical(other.crisis, crisis) || other.crisis == crisis));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,sender,text,sentAt,crisis);

@override
String toString() {
  return 'ChatMessage(id: $id, sender: $sender, text: $text, sentAt: $sentAt, crisis: $crisis)';
}


}

/// @nodoc
abstract mixin class $ChatMessageCopyWith<$Res>  {
  factory $ChatMessageCopyWith(ChatMessage value, $Res Function(ChatMessage) _then) = _$ChatMessageCopyWithImpl;
@useResult
$Res call({
 String id,@JsonKey(unknownEnumValue: ChatSender.unknown) ChatSender sender, String text, DateTime sentAt, bool crisis
});




}
/// @nodoc
class _$ChatMessageCopyWithImpl<$Res>
    implements $ChatMessageCopyWith<$Res> {
  _$ChatMessageCopyWithImpl(this._self, this._then);

  final ChatMessage _self;
  final $Res Function(ChatMessage) _then;

/// Create a copy of ChatMessage
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? id = null,Object? sender = null,Object? text = null,Object? sentAt = null,Object? crisis = null,}) {
  return _then(_self.copyWith(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as String,sender: null == sender ? _self.sender : sender // ignore: cast_nullable_to_non_nullable
as ChatSender,text: null == text ? _self.text : text // ignore: cast_nullable_to_non_nullable
as String,sentAt: null == sentAt ? _self.sentAt : sentAt // ignore: cast_nullable_to_non_nullable
as DateTime,crisis: null == crisis ? _self.crisis : crisis // ignore: cast_nullable_to_non_nullable
as bool,
  ));
}

}


/// Adds pattern-matching-related methods to [ChatMessage].
extension ChatMessagePatterns on ChatMessage {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _ChatMessage value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _ChatMessage() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _ChatMessage value)  $default,){
final _that = this;
switch (_that) {
case _ChatMessage():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _ChatMessage value)?  $default,){
final _that = this;
switch (_that) {
case _ChatMessage() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( String id, @JsonKey(unknownEnumValue: ChatSender.unknown)  ChatSender sender,  String text,  DateTime sentAt,  bool crisis)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _ChatMessage() when $default != null:
return $default(_that.id,_that.sender,_that.text,_that.sentAt,_that.crisis);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( String id, @JsonKey(unknownEnumValue: ChatSender.unknown)  ChatSender sender,  String text,  DateTime sentAt,  bool crisis)  $default,) {final _that = this;
switch (_that) {
case _ChatMessage():
return $default(_that.id,_that.sender,_that.text,_that.sentAt,_that.crisis);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( String id, @JsonKey(unknownEnumValue: ChatSender.unknown)  ChatSender sender,  String text,  DateTime sentAt,  bool crisis)?  $default,) {final _that = this;
switch (_that) {
case _ChatMessage() when $default != null:
return $default(_that.id,_that.sender,_that.text,_that.sentAt,_that.crisis);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _ChatMessage implements ChatMessage {
  const _ChatMessage({required this.id, @JsonKey(unknownEnumValue: ChatSender.unknown) required this.sender, required this.text, required this.sentAt, this.crisis = false});
  factory _ChatMessage.fromJson(Map<String, dynamic> json) => _$ChatMessageFromJson(json);

@override final  String id;
@override@JsonKey(unknownEnumValue: ChatSender.unknown) final  ChatSender sender;
@override final  String text;
@override final  DateTime sentAt;
@override@JsonKey() final  bool crisis;

/// Create a copy of ChatMessage
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$ChatMessageCopyWith<_ChatMessage> get copyWith => __$ChatMessageCopyWithImpl<_ChatMessage>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$ChatMessageToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _ChatMessage&&(identical(other.id, id) || other.id == id)&&(identical(other.sender, sender) || other.sender == sender)&&(identical(other.text, text) || other.text == text)&&(identical(other.sentAt, sentAt) || other.sentAt == sentAt)&&(identical(other.crisis, crisis) || other.crisis == crisis));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,sender,text,sentAt,crisis);

@override
String toString() {
  return 'ChatMessage(id: $id, sender: $sender, text: $text, sentAt: $sentAt, crisis: $crisis)';
}


}

/// @nodoc
abstract mixin class _$ChatMessageCopyWith<$Res> implements $ChatMessageCopyWith<$Res> {
  factory _$ChatMessageCopyWith(_ChatMessage value, $Res Function(_ChatMessage) _then) = __$ChatMessageCopyWithImpl;
@override @useResult
$Res call({
 String id,@JsonKey(unknownEnumValue: ChatSender.unknown) ChatSender sender, String text, DateTime sentAt, bool crisis
});




}
/// @nodoc
class __$ChatMessageCopyWithImpl<$Res>
    implements _$ChatMessageCopyWith<$Res> {
  __$ChatMessageCopyWithImpl(this._self, this._then);

  final _ChatMessage _self;
  final $Res Function(_ChatMessage) _then;

/// Create a copy of ChatMessage
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? id = null,Object? sender = null,Object? text = null,Object? sentAt = null,Object? crisis = null,}) {
  return _then(_ChatMessage(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as String,sender: null == sender ? _self.sender : sender // ignore: cast_nullable_to_non_nullable
as ChatSender,text: null == text ? _self.text : text // ignore: cast_nullable_to_non_nullable
as String,sentAt: null == sentAt ? _self.sentAt : sentAt // ignore: cast_nullable_to_non_nullable
as DateTime,crisis: null == crisis ? _self.crisis : crisis // ignore: cast_nullable_to_non_nullable
as bool,
  ));
}


}


/// @nodoc
mixin _$ChatSessionInfo {

 String get id; DateTime get startedAt; DateTime? get endedAt; bool get ended; String? get dominantEmotion; String? get preview;
/// Create a copy of ChatSessionInfo
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$ChatSessionInfoCopyWith<ChatSessionInfo> get copyWith => _$ChatSessionInfoCopyWithImpl<ChatSessionInfo>(this as ChatSessionInfo, _$identity);

  /// Serializes this ChatSessionInfo to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is ChatSessionInfo&&(identical(other.id, id) || other.id == id)&&(identical(other.startedAt, startedAt) || other.startedAt == startedAt)&&(identical(other.endedAt, endedAt) || other.endedAt == endedAt)&&(identical(other.ended, ended) || other.ended == ended)&&(identical(other.dominantEmotion, dominantEmotion) || other.dominantEmotion == dominantEmotion)&&(identical(other.preview, preview) || other.preview == preview));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,startedAt,endedAt,ended,dominantEmotion,preview);

@override
String toString() {
  return 'ChatSessionInfo(id: $id, startedAt: $startedAt, endedAt: $endedAt, ended: $ended, dominantEmotion: $dominantEmotion, preview: $preview)';
}


}

/// @nodoc
abstract mixin class $ChatSessionInfoCopyWith<$Res>  {
  factory $ChatSessionInfoCopyWith(ChatSessionInfo value, $Res Function(ChatSessionInfo) _then) = _$ChatSessionInfoCopyWithImpl;
@useResult
$Res call({
 String id, DateTime startedAt, DateTime? endedAt, bool ended, String? dominantEmotion, String? preview
});




}
/// @nodoc
class _$ChatSessionInfoCopyWithImpl<$Res>
    implements $ChatSessionInfoCopyWith<$Res> {
  _$ChatSessionInfoCopyWithImpl(this._self, this._then);

  final ChatSessionInfo _self;
  final $Res Function(ChatSessionInfo) _then;

/// Create a copy of ChatSessionInfo
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? id = null,Object? startedAt = null,Object? endedAt = freezed,Object? ended = null,Object? dominantEmotion = freezed,Object? preview = freezed,}) {
  return _then(_self.copyWith(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as String,startedAt: null == startedAt ? _self.startedAt : startedAt // ignore: cast_nullable_to_non_nullable
as DateTime,endedAt: freezed == endedAt ? _self.endedAt : endedAt // ignore: cast_nullable_to_non_nullable
as DateTime?,ended: null == ended ? _self.ended : ended // ignore: cast_nullable_to_non_nullable
as bool,dominantEmotion: freezed == dominantEmotion ? _self.dominantEmotion : dominantEmotion // ignore: cast_nullable_to_non_nullable
as String?,preview: freezed == preview ? _self.preview : preview // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}

}


/// Adds pattern-matching-related methods to [ChatSessionInfo].
extension ChatSessionInfoPatterns on ChatSessionInfo {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _ChatSessionInfo value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _ChatSessionInfo() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _ChatSessionInfo value)  $default,){
final _that = this;
switch (_that) {
case _ChatSessionInfo():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _ChatSessionInfo value)?  $default,){
final _that = this;
switch (_that) {
case _ChatSessionInfo() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( String id,  DateTime startedAt,  DateTime? endedAt,  bool ended,  String? dominantEmotion,  String? preview)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _ChatSessionInfo() when $default != null:
return $default(_that.id,_that.startedAt,_that.endedAt,_that.ended,_that.dominantEmotion,_that.preview);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( String id,  DateTime startedAt,  DateTime? endedAt,  bool ended,  String? dominantEmotion,  String? preview)  $default,) {final _that = this;
switch (_that) {
case _ChatSessionInfo():
return $default(_that.id,_that.startedAt,_that.endedAt,_that.ended,_that.dominantEmotion,_that.preview);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( String id,  DateTime startedAt,  DateTime? endedAt,  bool ended,  String? dominantEmotion,  String? preview)?  $default,) {final _that = this;
switch (_that) {
case _ChatSessionInfo() when $default != null:
return $default(_that.id,_that.startedAt,_that.endedAt,_that.ended,_that.dominantEmotion,_that.preview);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _ChatSessionInfo implements ChatSessionInfo {
  const _ChatSessionInfo({required this.id, required this.startedAt, this.endedAt, this.ended = false, this.dominantEmotion, this.preview});
  factory _ChatSessionInfo.fromJson(Map<String, dynamic> json) => _$ChatSessionInfoFromJson(json);

@override final  String id;
@override final  DateTime startedAt;
@override final  DateTime? endedAt;
@override@JsonKey() final  bool ended;
@override final  String? dominantEmotion;
@override final  String? preview;

/// Create a copy of ChatSessionInfo
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$ChatSessionInfoCopyWith<_ChatSessionInfo> get copyWith => __$ChatSessionInfoCopyWithImpl<_ChatSessionInfo>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$ChatSessionInfoToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _ChatSessionInfo&&(identical(other.id, id) || other.id == id)&&(identical(other.startedAt, startedAt) || other.startedAt == startedAt)&&(identical(other.endedAt, endedAt) || other.endedAt == endedAt)&&(identical(other.ended, ended) || other.ended == ended)&&(identical(other.dominantEmotion, dominantEmotion) || other.dominantEmotion == dominantEmotion)&&(identical(other.preview, preview) || other.preview == preview));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,startedAt,endedAt,ended,dominantEmotion,preview);

@override
String toString() {
  return 'ChatSessionInfo(id: $id, startedAt: $startedAt, endedAt: $endedAt, ended: $ended, dominantEmotion: $dominantEmotion, preview: $preview)';
}


}

/// @nodoc
abstract mixin class _$ChatSessionInfoCopyWith<$Res> implements $ChatSessionInfoCopyWith<$Res> {
  factory _$ChatSessionInfoCopyWith(_ChatSessionInfo value, $Res Function(_ChatSessionInfo) _then) = __$ChatSessionInfoCopyWithImpl;
@override @useResult
$Res call({
 String id, DateTime startedAt, DateTime? endedAt, bool ended, String? dominantEmotion, String? preview
});




}
/// @nodoc
class __$ChatSessionInfoCopyWithImpl<$Res>
    implements _$ChatSessionInfoCopyWith<$Res> {
  __$ChatSessionInfoCopyWithImpl(this._self, this._then);

  final _ChatSessionInfo _self;
  final $Res Function(_ChatSessionInfo) _then;

/// Create a copy of ChatSessionInfo
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? id = null,Object? startedAt = null,Object? endedAt = freezed,Object? ended = null,Object? dominantEmotion = freezed,Object? preview = freezed,}) {
  return _then(_ChatSessionInfo(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as String,startedAt: null == startedAt ? _self.startedAt : startedAt // ignore: cast_nullable_to_non_nullable
as DateTime,endedAt: freezed == endedAt ? _self.endedAt : endedAt // ignore: cast_nullable_to_non_nullable
as DateTime?,ended: null == ended ? _self.ended : ended // ignore: cast_nullable_to_non_nullable
as bool,dominantEmotion: freezed == dominantEmotion ? _self.dominantEmotion : dominantEmotion // ignore: cast_nullable_to_non_nullable
as String?,preview: freezed == preview ? _self.preview : preview // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}


}


/// @nodoc
mixin _$Helpline {

 String get key; String get name; String get number;
/// Create a copy of Helpline
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$HelplineCopyWith<Helpline> get copyWith => _$HelplineCopyWithImpl<Helpline>(this as Helpline, _$identity);

  /// Serializes this Helpline to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is Helpline&&(identical(other.key, key) || other.key == key)&&(identical(other.name, name) || other.name == name)&&(identical(other.number, number) || other.number == number));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,key,name,number);

@override
String toString() {
  return 'Helpline(key: $key, name: $name, number: $number)';
}


}

/// @nodoc
abstract mixin class $HelplineCopyWith<$Res>  {
  factory $HelplineCopyWith(Helpline value, $Res Function(Helpline) _then) = _$HelplineCopyWithImpl;
@useResult
$Res call({
 String key, String name, String number
});




}
/// @nodoc
class _$HelplineCopyWithImpl<$Res>
    implements $HelplineCopyWith<$Res> {
  _$HelplineCopyWithImpl(this._self, this._then);

  final Helpline _self;
  final $Res Function(Helpline) _then;

/// Create a copy of Helpline
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? key = null,Object? name = null,Object? number = null,}) {
  return _then(_self.copyWith(
key: null == key ? _self.key : key // ignore: cast_nullable_to_non_nullable
as String,name: null == name ? _self.name : name // ignore: cast_nullable_to_non_nullable
as String,number: null == number ? _self.number : number // ignore: cast_nullable_to_non_nullable
as String,
  ));
}

}


/// Adds pattern-matching-related methods to [Helpline].
extension HelplinePatterns on Helpline {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _Helpline value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _Helpline() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _Helpline value)  $default,){
final _that = this;
switch (_that) {
case _Helpline():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _Helpline value)?  $default,){
final _that = this;
switch (_that) {
case _Helpline() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( String key,  String name,  String number)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _Helpline() when $default != null:
return $default(_that.key,_that.name,_that.number);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( String key,  String name,  String number)  $default,) {final _that = this;
switch (_that) {
case _Helpline():
return $default(_that.key,_that.name,_that.number);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( String key,  String name,  String number)?  $default,) {final _that = this;
switch (_that) {
case _Helpline() when $default != null:
return $default(_that.key,_that.name,_that.number);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _Helpline implements Helpline {
  const _Helpline({required this.key, required this.name, required this.number});
  factory _Helpline.fromJson(Map<String, dynamic> json) => _$HelplineFromJson(json);

@override final  String key;
@override final  String name;
@override final  String number;

/// Create a copy of Helpline
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$HelplineCopyWith<_Helpline> get copyWith => __$HelplineCopyWithImpl<_Helpline>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$HelplineToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _Helpline&&(identical(other.key, key) || other.key == key)&&(identical(other.name, name) || other.name == name)&&(identical(other.number, number) || other.number == number));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,key,name,number);

@override
String toString() {
  return 'Helpline(key: $key, name: $name, number: $number)';
}


}

/// @nodoc
abstract mixin class _$HelplineCopyWith<$Res> implements $HelplineCopyWith<$Res> {
  factory _$HelplineCopyWith(_Helpline value, $Res Function(_Helpline) _then) = __$HelplineCopyWithImpl;
@override @useResult
$Res call({
 String key, String name, String number
});




}
/// @nodoc
class __$HelplineCopyWithImpl<$Res>
    implements _$HelplineCopyWith<$Res> {
  __$HelplineCopyWithImpl(this._self, this._then);

  final _Helpline _self;
  final $Res Function(_Helpline) _then;

/// Create a copy of Helpline
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? key = null,Object? name = null,Object? number = null,}) {
  return _then(_Helpline(
key: null == key ? _self.key : key // ignore: cast_nullable_to_non_nullable
as String,name: null == name ? _self.name : name // ignore: cast_nullable_to_non_nullable
as String,number: null == number ? _self.number : number // ignore: cast_nullable_to_non_nullable
as String,
  ));
}


}

// dart format on
