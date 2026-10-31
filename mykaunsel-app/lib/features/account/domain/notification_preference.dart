/// One e-mail the person can switch on or off (`GET /account/notification-preferences`). [label] and [description] are the server's words, used only
/// for a choice this version of the app has no words for.
class NotificationPreference {
  const NotificationPreference({required this.key, required this.label, required this.description, required this.enabled});

  factory NotificationPreference.fromJson(Map<String, dynamic> json) => NotificationPreference(
        key: json['key'] as String,
        label: (json['label'] as String?) ?? '',
        description: (json['description'] as String?) ?? '',
        enabled: json['enabled'] == true,
      );

  final String key;
  final String label;
  final String description;
  final bool enabled;
}
