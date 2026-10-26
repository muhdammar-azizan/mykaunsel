import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:mykaunsel/features/auth/domain/app_user.dart';
import 'package:mykaunsel/features/platform_status/domain/platform_status.dart';

/// The payloads below are copied from what the Laravel server really returns (GET /api/v1/status and /auth/me).
/// If the server changes a field name, this test fails here instead of in somebody's hand.
void main() {
  test('GET /status as the server sends it', () {
    const body = '''
    {
      "api_version": "v1",
      "app": {
        "min_version": "0.0.0",
        "update_required": false,
        "message": "This version of MyKaunsel is out of date. Please update the app to continue.",
        "store_url": null
      },
      "maintenance": {"on": false, "message": null},
      "registration": {
        "organization": {"open": true, "message": null},
        "counselor": {"open": true, "message": null},
        "individual": {"open": false, "message": "We are not accepting new individual sign-ups right now. Please check back soon."}
      }
    }''';

    final status = PlatformStatus.fromJson(jsonDecode(body) as Map<String, dynamic>);

    expect(status.maintenance.isOn, isFalse);
    expect(status.app.updateRequired, isFalse);
    expect(status.app.minVersion, '0.0.0');
    expect(status.app.storeUrl, isNull);
    expect(status.registration['individual']!.open, isFalse);
    expect(status.registration['individual']!.message, contains('individual'));
    expect(status.registration['organization']!.open, isTrue);
  });

  test('GET /status during maintenance and when an update is required', () {
    final maintenance = PlatformStatus.fromJson(jsonDecode('''
    {"api_version":"v1","app":{"min_version":"1.2.0","update_required":true,"message":"Update.","store_url":"https://play.example/app"},
     "maintenance":{"on":true,"message":"Back at 6 PM."},"registration":{}}''') as Map<String, dynamic>);

    expect(maintenance.maintenance.isOn, isTrue);
    expect(maintenance.maintenance.message, 'Back at 6 PM.');
    expect(maintenance.app.updateRequired, isTrue);
    expect(maintenance.app.storeUrl, 'https://play.example/app');
    expect(maintenance.registration, isEmpty);
  });

  test('the person from /auth/me as the server sends it', () {
    final user = AppUser.fromJson(jsonDecode('''
    {"id":42,"name":"Siti Aminah","email":"siti@example.com","email_verified":true,"avatar_url":null,"timezone":"Asia/Kuala_Lumpur"}''') as Map<String, dynamic>);

    expect(user.id, 42);
    expect(user.emailVerified, isTrue);
    expect(user.avatarUrl, isNull);
    expect(user.timezone, 'Asia/Kuala_Lumpur');
  });
}
