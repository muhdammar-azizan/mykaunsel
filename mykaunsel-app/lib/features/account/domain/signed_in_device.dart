import 'package:freezed_annotation/freezed_annotation.dart';

part 'signed_in_device.freezed.dart';
part 'signed_in_device.g.dart';

/// A phone signed in to this account. Never anything that could sign in: just what it is called and when it was used.
@freezed
abstract class SignedInDevice with _$SignedInDevice {
  const factory SignedInDevice({
    required int id,
    required String name,
    @Default(false) bool current,
    DateTime? lastUsedAt,
    DateTime? createdAt,
  }) = _SignedInDevice;

  factory SignedInDevice.fromJson(Map<String, dynamic> json) => _$SignedInDeviceFromJson(json);
}
