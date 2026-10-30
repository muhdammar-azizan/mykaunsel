import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mykaunsel/core/config/app_config.dart';
import 'package:mykaunsel/core/config/app_info.dart';
import 'package:mykaunsel/core/error/app_failure.dart';
import 'package:mykaunsel/core/network/api_client.dart';
import 'package:mykaunsel/features/booking/data/availability_api.dart';
import 'package:mykaunsel/features/booking/domain/availability.dart';
import 'package:mykaunsel/features/counselors/domain/counselor.dart';

import '../../support/fakes.dart';

/// The real HttpAvailabilityApi against answers shaped like the server's (docs/AVAILABILITY.md).
void main() {
  HttpAvailabilityApi build(FakeHttpAdapter adapter) {
    final client = ApiClient(
      config: const AppConfig(flavor: Flavor.dev, apiBaseUrl: 'http://test.local'),
      info: const AppInfo(version: '1.0.0', platform: 'android'),
      readToken: () async => 'tok',
      onFailure: (_, {required hadToken}) {},
      dio: Dio()..httpClientAdapter = adapter,
    );

    return HttpAvailabilityApi(client);
  }

  Map<String, dynamic> body({String state = 'available', String mode = 'online'}) => {
        'counselor': {'id': '01HX', 'display_name': 'Aina', 'avatar_url': null, 'verified': true, 'modes_offered': ['online', 'physical'], 'accepts_requests': false},
        'time_zone': 'Asia/Kuala_Lumpur',
        'booking_window': {'earliest_start': '2026-11-02T11:00:00+08:00', 'last_date': '2026-12-02', 'min_lead_hours': 2},
        'days': [
          {
            'date': '2026-11-03',
            'counts': {'online': 1, 'physical': 1},
            'slots': [
              {'id': '01S1', 'starts_at': '2026-11-03T10:00:00+08:00', 'duration_minutes': 60, 'mode': mode, 'state': state, 'location': null},
              {
                'id': '01S2',
                'starts_at': '2026-11-03T14:00:00+08:00',
                'duration_minutes': 60,
                'mode': 'physical',
                'state': 'available',
                'location': {'name': 'Klinik Damai', 'city': 'Kuantan'},
              },
            ],
          },
        ],
        'next_from': '2026-11-16',
      };

  test('reads the answer, and asks for today when no date is given', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(200, body()));
    final result = await build(adapter).fetch('01HX');

    expect(adapter.requests.single.path, '/counselors/01HX/availability');
    expect(adapter.requests.single.queryParameters.containsKey('from'), isFalse);
    expect(result.counselor.displayName, 'Aina');
    expect(result.days.single.slots.first.startsAt.toUtc(), DateTime.utc(2026, 11, 3, 2));
    expect(result.days.single.slots.last.location?.city, 'Kuantan');
    expect(result.nextFrom, '2026-11-16');
  });

  test('sends the date to continue from, and never sends a mode', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(200, body()));
    await build(adapter).fetch('01HX', from: '2026-11-16');

    expect(adapter.requests.single.queryParameters, {'from': '2026-11-16'});
  });

  test('an id with odd characters cannot change the path', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(200, body()));
    await build(adapter).fetch('a/b?c');

    expect(adapter.requests.single.path, contains('a%2Fb%3Fc'));
  });

  test('a state or a mode added later by the server does not break the screen', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(200, body(state: 'on_hold', mode: 'phone')));
    final slot = (await build(adapter).fetch('01HX')).days.single.slots.first;

    expect(slot.state, SlotState.unknown);
    expect(slot.isAvailable, isFalse);
    expect(slot.mode, SessionMode.unknown);
  });

  test('404 is NotFound whatever the reason', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(404, {'message': 'x', 'code': 'counselor_unavailable'}));

    await expectLater(build(adapter).fetch('01HX'), throwsA(isA<NotFoundFailure>()));
  });

  test('403 email_not_verified is its own failure', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(403, {'message': 'Verify first.', 'code': 'email_not_verified'}));

    await expectLater(build(adapter).fetch('01HX'), throwsA(isA<EmailNotVerifiedFailure>()));
  });

  test('429 carries the wait', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(429, {'message': 'slow'}, headers: {'retry-after': ['20']}));

    await expectLater(build(adapter).fetch('01HX'), throwsA(isA<RateLimitedFailure>().having((f) => f.retryAfterSeconds, 'wait', 20)));
  });
}
