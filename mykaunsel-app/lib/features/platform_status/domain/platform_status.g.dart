// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'platform_status.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

_PlatformStatus _$PlatformStatusFromJson(Map<String, dynamic> json) =>
    _PlatformStatus(
      maintenance: MaintenanceStatus.fromJson(
        json['maintenance'] as Map<String, dynamic>,
      ),
      app: AppVersionStatus.fromJson(json['app'] as Map<String, dynamic>),
      registration:
          (json['registration'] as Map<String, dynamic>?)?.map(
            (k, e) => MapEntry(
              k,
              RegistrationStatus.fromJson(e as Map<String, dynamic>),
            ),
          ) ??
          const {},
    );

Map<String, dynamic> _$PlatformStatusToJson(_PlatformStatus instance) =>
    <String, dynamic>{
      'maintenance': instance.maintenance,
      'app': instance.app,
      'registration': instance.registration,
    };

_MaintenanceStatus _$MaintenanceStatusFromJson(Map<String, dynamic> json) =>
    _MaintenanceStatus(
      isOn: json['on'] as bool,
      message: json['message'] as String?,
    );

Map<String, dynamic> _$MaintenanceStatusToJson(_MaintenanceStatus instance) =>
    <String, dynamic>{'on': instance.isOn, 'message': instance.message};

_AppVersionStatus _$AppVersionStatusFromJson(Map<String, dynamic> json) =>
    _AppVersionStatus(
      updateRequired: json['update_required'] as bool,
      minVersion: json['min_version'] as String?,
      message: json['message'] as String?,
      storeUrl: json['store_url'] as String?,
    );

Map<String, dynamic> _$AppVersionStatusToJson(_AppVersionStatus instance) =>
    <String, dynamic>{
      'update_required': instance.updateRequired,
      'min_version': instance.minVersion,
      'message': instance.message,
      'store_url': instance.storeUrl,
    };

_RegistrationStatus _$RegistrationStatusFromJson(Map<String, dynamic> json) =>
    _RegistrationStatus(
      open: json['open'] as bool,
      message: json['message'] as String?,
    );

Map<String, dynamic> _$RegistrationStatusToJson(_RegistrationStatus instance) =>
    <String, dynamic>{'open': instance.open, 'message': instance.message};
