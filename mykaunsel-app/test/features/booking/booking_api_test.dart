import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mykaunsel/core/config/app_config.dart';
import 'package:mykaunsel/core/config/app_info.dart';
import 'package:mykaunsel/core/error/app_failure.dart';
import 'package:mykaunsel/core/network/api_client.dart';
import 'package:mykaunsel/features/booking/data/booking_api.dart';
import 'package:mykaunsel/features/counselors/domain/counselor.dart';

import '../../support/fakes.dart';

/// The real HttpBookingApi against answers shaped like the server's (docs/AVAILABILITY.md, "The confirm step").
void main() {
  HttpBookingApi build(FakeHttpAdapter adapter) {
    final client = ApiClient(
      config: const AppConfig(flavor: Flavor.dev, apiBaseUrl: 'http://test.local'),
      info: const AppInfo(version: '1.0.0', platform: 'android'),
      readToken: () async => 'tok',
      onFailure: (_, {required hadToken}) {},
      dio: Dio()..httpClientAdapter = adapter,
    );

    return HttpBookingApi(client);
  }

  const details = {
    'valid': true,
    'reason': null,
    'slot': '01s',
    'counselor': {'id': '01c', 'display_name': 'Aina', 'verified': true},
    'starts_at': '2026-11-03T10:00:00+08:00',
    'ends_at': '2026-11-03T10:50:00+08:00',
    'duration_minutes': 50,
    'mode': 'physical',
    'location': {'name': 'Klinik Damai', 'city': 'Kuantan'},
    'cancellation_deadline_at': null,
    'cancellation_hours': 24,
    'max_reschedules': 2,
    'meeting_link_visible_minutes': 15,
    'upcoming_sessions': 2,
    'max_upcoming_sessions': 3,
  };

  test('the preview asks for the slot and reads every detail, including a missing cancellation deadline', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(200, details));
    final preview = await build(adapter).preview('01s');

    expect(adapter.requests.single.path, '/bookings/preview');
    expect(adapter.requests.single.queryParameters, {'slot': '01s'});
    expect(preview.valid, isTrue);
    final d = preview.details!;
    expect(d.counselor.displayName, 'Aina');
    expect(d.startsAt.toUtc(), DateTime.utc(2026, 11, 3, 2));
    expect(d.endsAt.toUtc(), DateTime.utc(2026, 11, 3, 2, 50));
    expect(d.mode, SessionMode.physical);
    expect(d.location?.city, 'Kuantan');
    expect(d.cancellationDeadlineAt, isNull);
    expect(d.maxUpcomingSessions, 3);
  });

  test('a gone time comes back with no details at all', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(200, {'valid': false, 'reason': 'slot_taken'}));
    final preview = await build(adapter).preview('01s');

    expect(preview.valid, isFalse);
    expect(preview.reason, 'slot_taken');
    expect(preview.details, isNull);
  });

  test('a mode added later by the server becomes unknown, not a crash', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(200, {...details, 'mode': 'phone'}));

    expect((await build(adapter).preview('01s')).details!.mode, SessionMode.unknown);
  });

  test('the booking sends the key as a HEADER and the acknowledgement and the expected values in the body', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(201, {
          'booking': {
            'id': '01bk',
            'status': 'confirmed',
            'starts_at': '2026-11-03T10:00:00+08:00',
            'ends_at': '2026-11-03T10:50:00+08:00',
            'duration_minutes': 50,
            'mode': 'online',
            'counselor': {'display_name': 'Aina'},
            'location': null,
          },
        }));

    final created = await build(adapter).create(
      idempotencyKey: 'a-key',
      slotId: '01s',
      expectedStartsAt: DateTime.parse('2026-11-03T10:00:00+08:00'),
      expectedDurationMinutes: 50,
      expectedMode: 'online',
    );

    final request = adapter.requests.single;
    expect(request.method, 'POST');
    expect(request.path, '/bookings');
    expect(request.headers['Idempotency-Key'], 'a-key');
    expect(request.headers['Authorization'], 'Bearer tok');
    expect(request.data, {
      'slot': '01s',
      'acknowledged_policy': true,
      'expected_starts_at': '2026-11-03T02:00:00.000Z',
      'expected_duration_minutes': 50,
      'expected_mode': 'online',
    });
    expect(created.id, '01bk');
    expect(created.counselorName, 'Aina');
    expect(created.mode, SessionMode.online);
    expect(created.location, isNull);
  });

  test('every 409 keeps its code, so the screen can tell them apart', () async {
    for (final code in ['slot_taken', 'slot_changed', 'time_conflict', 'upcoming_limit_reached', 'request_in_progress']) {
      final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(409, {'message': 'x', 'code': code}));

      await expectLater(
        build(adapter).create(idempotencyKey: 'k', slotId: 's', expectedStartsAt: DateTime.utc(2026), expectedDurationMinutes: 50, expectedMode: 'online'),
        throwsA(isA<ConflictFailure>().having((f) => f.code, 'code', code)),
      );
    }
  });

  test('404 is NotFound whatever the reason, and 403 email_not_verified is its own failure', () async {
    final missing = FakeHttpAdapter((_) => FakeHttpAdapter.json(404, {'message': 'x', 'code': 'counselor_unavailable'}));
    final unverified = FakeHttpAdapter((_) => FakeHttpAdapter.json(403, {'message': 'x', 'code': 'email_not_verified'}));

    await expectLater(build(missing).preview('s'), throwsA(isA<NotFoundFailure>()));
    await expectLater(build(unverified).preview('s'), throwsA(isA<EmailNotVerifiedFailure>()));
  });
}
