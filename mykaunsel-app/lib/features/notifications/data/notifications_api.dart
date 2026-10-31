import '../../../core/network/api_client.dart';
import '../domain/app_notification.dart';

/// The person's notifications in the app (docs/NOTIFICATIONS.md on the server). Throws only an [AppFailure]; [NotFoundFailure] when one to be marked
/// read is not there (it was never theirs, or it was cleared).
abstract class NotificationsApi {
  Future<NotificationsPage> list({int page = 1});

  /// The bell's number.
  Future<int> unread();

  /// Marks one read. Safe to repeat. Answers the unread number as it now is.
  Future<int> markRead(String id);

  Future<int> markAllRead();
}

class HttpNotificationsApi implements NotificationsApi {
  HttpNotificationsApi(this._client);

  final ApiClient _client;

  @override
  Future<NotificationsPage> list({int page = 1}) async {
    final response = await _client.get('/notifications', query: {'page': '$page'});

    return NotificationsPage(
      items: [for (final item in (response.data['data'] as List? ?? const [])) AppNotification.fromJson(Map<String, dynamic>.from(item as Map))],
      unread: (response.data['unread'] as num?)?.toInt() ?? 0,
      page: (response.data['page'] as num?)?.toInt() ?? 1,
      lastPage: (response.data['last_page'] as num?)?.toInt() ?? 1,
    );
  }

  @override
  Future<int> unread() async {
    final response = await _client.get('/notifications/unread-count');

    return (response.data['unread'] as num?)?.toInt() ?? 0;
  }

  @override
  Future<int> markRead(String id) async {
    final response = await _client.post('/notifications/${Uri.encodeComponent(id)}/read');

    return (response.data['unread'] as num?)?.toInt() ?? 0;
  }

  @override
  Future<int> markAllRead() async {
    final response = await _client.post('/notifications/read-all');

    return (response.data['unread'] as num?)?.toInt() ?? 0;
  }
}
