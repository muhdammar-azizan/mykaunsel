import 'dart:convert';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:mykaunsel/core/config/app_config.dart';
import 'package:mykaunsel/core/config/app_info.dart';
import 'package:mykaunsel/core/error/app_failure.dart';
import 'package:mykaunsel/core/network/api_client.dart';
import 'package:mykaunsel/features/account/data/account_api.dart';
import 'package:mykaunsel/features/account/domain/signed_in_device.dart';
import 'package:mykaunsel/shared/format/last_used.dart';
import 'package:mykaunsel/l10n/app_localizations_en.dart';

import '../../support/fakes.dart';

void main() {
  setUpAll(() => initializeDateFormatting('en'));

  HttpAccountApi build(FakeHttpAdapter adapter) {
    return HttpAccountApi(ApiClient(
      config: const AppConfig(flavor: Flavor.dev, apiBaseUrl: 'http://test.local'),
      info: const AppInfo(version: '1.0.0', platform: 'android'),
      readToken: () async => '5|mkp_abc',
      onFailure: (_, {required hadToken}) {},
      dio: Dio()..httpClientAdapter = adapter,
    ));
  }

  const user = {'id': 7, 'name': 'Siti Nur Aminah', 'email': 'siti@example.com', 'email_verified': true, 'avatar_url': null, 'timezone': 'Asia/Singapore'};

  test('the profile is sent with PATCH and the answer is the person', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(200, {'user': user}));

    final result = await build(adapter).updateProfile(name: 'Siti Nur Aminah', timezone: 'Asia/Singapore');

    expect(adapter.requests.single.method, 'PATCH');
    expect(adapter.requests.single.path, '/account/profile');
    expect(adapter.requests.single.data, {'name': 'Siti Nur Aminah', 'timezone': 'Asia/Singapore'});
    expect(adapter.requests.single.headers['Authorization'], 'Bearer 5|mkp_abc');
    expect(result.name, 'Siti Nur Aminah');
    expect(result.timezone, 'Asia/Singapore');
  });

  test('a new password is sent with PUT, the new one twice, and nothing else', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(200, {'message': 'Password updated.'}));

    await build(adapter).changePassword(currentPassword: 'Old-Pass-123!', newPassword: 'New-Pass-456!');

    expect(adapter.requests.single.method, 'PUT');
    expect(adapter.requests.single.path, '/account/password');
    expect(adapter.requests.single.data, {'current_password': 'Old-Pass-123!', 'password': 'New-Pass-456!', 'password_confirmation': 'New-Pass-456!'});
  });

  test('a wrong current password is a ValidationFailure on that field', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(422, {'message': 'That password is not correct.', 'errors': {'current_password': ['That password is not correct.']}}));

    await expectLater(
      build(adapter).changePassword(currentPassword: 'wrong', newPassword: 'New-Pass-456!'),
      throwsA(isA<ValidationFailure>().having((f) => f.errorFor('current_password'), 'message', 'That password is not correct.')),
    );
  });

  test('too many tries is a RateLimitedFailure with the wait', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(429, {'message': 'Too Many Attempts.'}, headers: {'retry-after': ['40']}));

    await expectLater(build(adapter).changePassword(currentPassword: 'x', newPassword: 'y'), throwsA(isA<RateLimitedFailure>().having((f) => f.retryAfterSeconds, 'wait', 40)));
  });

  test('the list of phones is read from the data key (never a token in it)', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(200, {
          'data': [
            {'id': 4, 'name': 'Google Pixel 8', 'current': true, 'last_used_at': '2026-11-02T08:30:00+08:00', 'created_at': '2026-10-01T09:00:00+08:00'},
            {'id': 2, 'name': 'Old Tablet', 'current': false, 'last_used_at': null, 'created_at': '2026-09-01T09:00:00+08:00'},
          ],
        }));

    final devices = await build(adapter).devices();

    expect(devices.map((d) => d.name), ['Google Pixel 8', 'Old Tablet']);
    expect(devices.first.current, isTrue);
    expect(devices.first.lastUsedAt, DateTime.utc(2026, 11, 2, 0, 30));
    expect(devices.last.lastUsedAt, isNull);
  });

  test('signing another phone out posts the password to that phone\'s own address', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(200, {'message': 'That device was signed out.'}));

    await build(adapter).revokeDevice(9, currentPassword: 'Str0ng-Pass-123');

    expect(adapter.requests.single.method, 'POST');
    expect(adapter.requests.single.path, '/account/devices/9/revoke');
    expect(adapter.requests.single.data, {'current_password': 'Str0ng-Pass-123'});
  });

  test('a phone that is gone is NotFoundFailure', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(404, {'message': 'Not Found'}));

    await expectLater(build(adapter).revokeDevice(9, currentPassword: 'x'), throwsA(isA<NotFoundFailure>()));
  });

  group('"last used"', () {
    final l10n = AppLocalizationsEn();
    final now = DateTime.utc(2026, 11, 2, 1);

    String label(Duration ago) => formatLastUsed(l10n, now.subtract(ago), now: now, locale: 'en');

    test('reads naturally from seconds to weeks', () {
      expect(label(const Duration(seconds: 20)), 'Active just now');
      expect(label(const Duration(minutes: 1)), 'Last used 1 minute ago');
      expect(label(const Duration(minutes: 45)), 'Last used 45 minutes ago');
      expect(label(const Duration(hours: 1)), 'Last used 1 hour ago');
      expect(label(const Duration(hours: 23)), 'Last used 23 hours ago');
      expect(label(const Duration(days: 1)), 'Last used yesterday');
      expect(label(const Duration(days: 4)), 'Last used 4 days ago');
    });

    test('older than a week is a date, and never used says so', () {
      expect(label(const Duration(days: 30)).startsWith('Last used '), isTrue);
      expect(label(const Duration(days: 30)), contains('2026'));
      expect(formatLastUsed(l10n, null, now: now, locale: 'en'), 'Not used yet');
    });
  });

  test('the real list of phones, as the server sent it, is understood', () {
    final json = jsonDecode(File('test/fixtures/account_devices.json').readAsStringSync()) as Map<String, dynamic>;
    final devices = (json['data'] as List).map((d) => SignedInDevice.fromJson(d as Map<String, dynamic>)).toList();

    expect(devices, hasLength(2));
    expect(devices.first.current, isTrue);
    expect(devices.first.name, 'Google Pixel 8');
    expect(devices.first.lastUsedAt, isNotNull);
    expect(devices.last.lastUsedAt, isNull);
    expect(json.toString(), isNot(contains('mkp_'))); // never a token
  });

  test('a device parses with missing optional fields', () {
    final device = SignedInDevice.fromJson({'id': 1, 'name': 'Phone'});

    expect(device.current, isFalse);
    expect(device.lastUsedAt, isNull);
  });
}
