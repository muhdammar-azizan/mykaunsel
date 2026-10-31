import '../../../core/network/api_client.dart';
import '../../auth/domain/app_user.dart';
import '../domain/notification_preference.dart';
import '../domain/signed_in_device.dart';

/// The signed-in person's own account (docs/API.md "Account"). Throws only an [AppFailure].
abstract class AccountApi {
  /// Name and time zone. Throws [ValidationFailure] with a message per field.
  Future<AppUser> updateProfile({required String name, required String timezone});

  /// Throws [ValidationFailure] on `current_password` (wrong) or `password` (weak / same), or [RateLimitedFailure].
  /// On success the server has signed every OTHER phone out; this one stays.
  Future<void> changePassword({required String currentPassword, required String newPassword});

  /// Agrees to (true) or withdraws (false) AI processing, which the chat needs. The server records every change. Returns the person as they now are.
  Future<AppUser> setAiConsent(bool consent);

  /// The e-mails the person can switch off (e-mail only: the app has no push notifications).
  Future<List<NotificationPreference>> notificationPreferences();

  /// Saves one choice; answers the list as it now is on the server.
  Future<List<NotificationPreference>> setNotificationPreference(String key, bool enabled);

  Future<List<SignedInDevice>> devices();

  /// Signs ANOTHER phone out. Needs the current password (same failures as [changePassword]).
  Future<void> revokeDevice(int id, {required String currentPassword});
}

class HttpAccountApi implements AccountApi {
  HttpAccountApi(this._client);

  final ApiClient _client;

  @override
  Future<AppUser> updateProfile({required String name, required String timezone}) async {
    final response = await _client.patch('/account/profile', data: {'name': name, 'timezone': timezone});

    return AppUser.fromJson(Map<String, dynamic>.from(response.data['user'] as Map));
  }

  @override
  Future<AppUser> setAiConsent(bool consent) async {
    final response = await _client.put('/account/ai-consent', data: {'consent': consent});

    return AppUser.fromJson(Map<String, dynamic>.from(response.data['user'] as Map));
  }

  @override
  Future<List<NotificationPreference>> notificationPreferences() async {
    final response = await _client.get('/account/notification-preferences');

    return _preferences(response.data);
  }

  @override
  Future<List<NotificationPreference>> setNotificationPreference(String key, bool enabled) async {
    final response = await _client.put('/account/notification-preferences', data: {'key': key, 'enabled': enabled});

    return _preferences(response.data);
  }

  List<NotificationPreference> _preferences(dynamic body) =>
      [for (final item in (body['preferences'] as List? ?? const [])) NotificationPreference.fromJson(Map<String, dynamic>.from(item as Map))];

  @override
  Future<void> changePassword({required String currentPassword, required String newPassword}) async {
    await _client.put('/account/password', data: {
      'current_password': currentPassword,
      'password': newPassword,
      'password_confirmation': newPassword,
    });
  }

  @override
  Future<List<SignedInDevice>> devices() async {
    final response = await _client.get('/account/devices');

    return (response.data['data'] as List).map((json) => SignedInDevice.fromJson(Map<String, dynamic>.from(json as Map))).toList();
  }

  @override
  Future<void> revokeDevice(int id, {required String currentPassword}) async {
    await _client.post('/account/devices/$id/revoke', data: {'current_password': currentPassword});
  }
}
