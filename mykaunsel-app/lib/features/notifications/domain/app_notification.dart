/// What a notification is about, when it has a page of its own in the app: a booking or a time request (by public id).
class NotificationTarget {
  const NotificationTarget({required this.kind, required this.id});

  factory NotificationTarget.fromJson(Map<String, dynamic> json) => NotificationTarget(kind: json['kind'] as String, id: json['id'] as String);

  /// `booking` or `booking_request`.
  final String kind;
  final String id;
}

/// One thing that happened to the person that they did not do themselves (docs/NOTIFICATIONS.md on the server). The app writes the sentence from
/// [type]; [title] is the server's English fallback for a type this version does not know yet.
class AppNotification {
  const AppNotification({
    required this.id,
    required this.type,
    required this.title,
    this.label,
    this.count = 1,
    required this.occurredAt,
    required this.read,
    this.target,
  });

  factory AppNotification.fromJson(Map<String, dynamic> json) => AppNotification(
        id: json['id'] as String,
        type: json['type'] as String,
        title: json['title'] as String? ?? '',
        label: json['label'] as String?,
        count: (json['count'] as num?)?.toInt() ?? 1,
        occurredAt: DateTime.parse(json['occurred_at'] as String),
        read: json['read'] as bool? ?? false,
        target: json['target'] == null ? null : NotificationTarget.fromJson(Map<String, dynamic>.from(json['target'] as Map)),
      );

  final String id;
  final String type;
  final String title;

  /// A counselor's or an organization's name; nothing else.
  final String? label;
  final int count;
  final DateTime occurredAt;
  final bool read;
  final NotificationTarget? target;

  AppNotification asRead() => AppNotification(id: id, type: type, title: title, label: label, count: count, occurredAt: occurredAt, read: true, target: target);
}

class NotificationsPage {
  const NotificationsPage({required this.items, required this.unread, required this.page, required this.lastPage});

  final List<AppNotification> items;
  final int unread;
  final int page;
  final int lastPage;
}
