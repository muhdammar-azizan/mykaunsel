import 'package:freezed_annotation/freezed_annotation.dart';

part 'platform_status.freezed.dart';
part 'platform_status.g.dart';

/// What `GET /status` says: asked first, before anything else is shown.
@freezed
abstract class PlatformStatus with _$PlatformStatus {
  const factory PlatformStatus({
    required MaintenanceStatus maintenance,
    required AppVersionStatus app,
    @Default({}) Map<String, RegistrationStatus> registration,

    /// Where people write for help and for a copy of their data; null when the server has none set.
    String? supportEmail,
  }) = _PlatformStatus;

  factory PlatformStatus.fromJson(Map<String, dynamic> json) => _$PlatformStatusFromJson(json);
}

@freezed
abstract class MaintenanceStatus with _$MaintenanceStatus {
  const factory MaintenanceStatus({
    @JsonKey(name: 'on') required bool isOn,
    String? message,
  }) = _MaintenanceStatus;

  factory MaintenanceStatus.fromJson(Map<String, dynamic> json) => _$MaintenanceStatusFromJson(json);
}

@freezed
abstract class AppVersionStatus with _$AppVersionStatus {
  const factory AppVersionStatus({
    required bool updateRequired,
    String? minVersion,
    String? message,
    String? storeUrl,
  }) = _AppVersionStatus;

  factory AppVersionStatus.fromJson(Map<String, dynamic> json) => _$AppVersionStatusFromJson(json);
}

@freezed
abstract class RegistrationStatus with _$RegistrationStatus {
  const factory RegistrationStatus({
    required bool open,
    String? message,
  }) = _RegistrationStatus;

  factory RegistrationStatus.fromJson(Map<String, dynamic> json) => _$RegistrationStatusFromJson(json);
}
