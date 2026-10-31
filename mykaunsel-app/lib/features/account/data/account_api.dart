import '../../../core/network/api_client.dart';
import '../../auth/domain/app_user.dart';
import '../domain/my_organization.dart';
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

  /// The organization the person belongs to or waits to join (null when none), and the invitations waiting for them.
  Future<OrganizationOverview> organization();

  /// Accepts an invitation: a member at once. Throws [ConflictFailure] (`in_other_organization`, ...) or [NotFoundFailure] (no longer open).
  Future<OrganizationOverview> acceptInvitation(String id);

  /// Says no. Answers the invitations that are left. Throws [NotFoundFailure] when it is no longer open.
  Future<List<OrgInvitation>> declineInvitation(String id);

  /// Asks to join with a reference code. Throws [ValidationFailure] (the code is not valid or has expired) or [ConflictFailure] with code
  /// `waiting_approval`, `already_member` or `in_other_organization` (the message says which organization), or [RateLimitedFailure].
  Future<MyOrganization> joinOrganization(String code);

  /// Leaves the organization (or withdraws the request). Throws [NotFoundFailure] when there is none.
  Future<LeftOrganization> leaveOrganization();

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
  Future<OrganizationOverview> organization() async {
    final response = await _client.get('/account/organization');

    return OrganizationOverview.fromJson(Map<String, dynamic>.from(response.data as Map));
  }

  @override
  Future<OrganizationOverview> acceptInvitation(String id) async {
    final response = await _client.post('/account/organization/invitations/$id/accept');

    return OrganizationOverview.fromJson(Map<String, dynamic>.from(response.data as Map));
  }

  @override
  Future<List<OrgInvitation>> declineInvitation(String id) async {
    final response = await _client.post('/account/organization/invitations/$id/decline');

    return OrganizationOverview.fromJson(Map<String, dynamic>.from(response.data as Map)).invitations;
  }

  @override
  Future<MyOrganization> joinOrganization(String code) async {
    final response = await _client.post('/account/organization/join', data: {'code': code});

    return MyOrganization.fromJson(Map<String, dynamic>.from(response.data['organization'] as Map));
  }

  @override
  Future<LeftOrganization> leaveOrganization() async {
    final response = await _client.delete('/account/organization');

    return LeftOrganization.fromJson(Map<String, dynamic>.from(response.data['left'] as Map));
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
