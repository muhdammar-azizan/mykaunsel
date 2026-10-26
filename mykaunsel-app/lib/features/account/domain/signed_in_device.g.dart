// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'signed_in_device.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

_SignedInDevice _$SignedInDeviceFromJson(Map<String, dynamic> json) =>
    _SignedInDevice(
      id: (json['id'] as num).toInt(),
      name: json['name'] as String,
      current: json['current'] as bool? ?? false,
      lastUsedAt: json['last_used_at'] == null
          ? null
          : DateTime.parse(json['last_used_at'] as String),
      createdAt: json['created_at'] == null
          ? null
          : DateTime.parse(json['created_at'] as String),
    );

Map<String, dynamic> _$SignedInDeviceToJson(_SignedInDevice instance) =>
    <String, dynamic>{
      'id': instance.id,
      'name': instance.name,
      'current': instance.current,
      'last_used_at': instance.lastUsedAt?.toIso8601String(),
      'created_at': instance.createdAt?.toIso8601String(),
    };
