import 'package:freezed_annotation/freezed_annotation.dart';

part 'app_user.freezed.dart';
part 'app_user.g.dart';

/// The signed-in person, as much as the app is allowed to know (`GET /auth/me`).
@freezed
abstract class AppUser with _$AppUser {
  const factory AppUser({
    required int id,
    required String name,
    required String email,
    @Default(false) bool emailVerified,
    String? avatarUrl,
    String? timezone,

    /// Whether the person agreed to AI processing, which the chat needs.
    @Default(false) bool aiConsent,
  }) = _AppUser;

  factory AppUser.fromJson(Map<String, dynamic> json) => _$AppUserFromJson(json);
}
