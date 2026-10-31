/// The organization a person belongs to, or has asked to join with a reference code. A client has only one.
class MyOrganization {
  const MyOrganization({required this.name, required this.waiting, this.graceDays = 7});

  factory MyOrganization.fromJson(Map<String, dynamic> json) => MyOrganization(
        name: json['name'] as String,
        waiting: json['status'] == 'waiting',
        graceDays: (json['grace_days'] as num?)?.toInt() ?? 7,
      );

  final String name;

  /// Asked with a code and not approved yet: nothing of the organization is open to them until then.
  final bool waiting;

  /// When they leave, sessions starting within this many days still go ahead (the server decides; this is only what the screen says).
  final int graceDays;
}

/// What leaving did to the person's sessions, as the server reports it.
class LeftOrganization {
  const LeftOrganization({required this.name, required this.cancelled, required this.kept});

  factory LeftOrganization.fromJson(Map<String, dynamic> json) => LeftOrganization(
        name: json['organization'] as String,
        cancelled: (json['cancelled'] as num).toInt(),
        kept: (json['kept'] as num).toInt(),
      );

  final String name;

  /// Later sessions that were cancelled.
  final int cancelled;

  /// Sessions in the next few days that still go ahead.
  final int kept;
}
