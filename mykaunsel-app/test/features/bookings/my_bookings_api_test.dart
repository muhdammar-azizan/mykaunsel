import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mykaunsel/core/config/app_config.dart';
import 'package:mykaunsel/core/config/app_info.dart';
import 'package:mykaunsel/core/error/app_failure.dart';
import 'package:mykaunsel/core/network/api_client.dart';
import 'package:mykaunsel/features/bookings/data/my_bookings_api.dart';
import 'package:mykaunsel/features/bookings/domain/my_booking.dart';
import 'package:mykaunsel/features/counselors/domain/counselor.dart';

import '../../support/fakes.dart';

/// The real HttpMyBookingsApi against answers shaped like the server's (docs/AVAILABILITY.md, "My bookings").
void main() {
  HttpMyBookingsApi build(FakeHttpAdapter adapter) {
    final client = ApiClient(
      config: const AppConfig(flavor: Flavor.dev, apiBaseUrl: 'http://test.local'),
      info: const AppInfo(version: '1.0.0', platform: 'android'),
      readToken: () async => 'tok',
      onFailure: (_, {required hadToken}) {},
      dio: Dio()..httpClientAdapter = adapter,
    );

    return HttpMyBookingsApi(client);
  }

  Map<String, dynamic> item({String status = 'confirmed', String mode = 'online'}) => {
        'id': '01bk',
        'status': status,
        'starting_soon': true,
        'starts_at': '2026-11-03T10:00:00+08:00',
        'ends_at': '2026-11-03T10:50:00+08:00',
        'duration_minutes': 50,
        'mode': mode,
        'counselor': {'id': '01c', 'display_name': 'Aina'},
        'location': null,
      };

  test('the list asks for the scope, and for a page only after the first', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(200, {'data': [item()], 'page': 1, 'last_page': 3, 'total': 50}));
    final api = build(adapter);

    final first = await api.list(BookingsScope.past);
    await api.list(BookingsScope.past, page: 2);
    await api.list(BookingsScope.upcoming);

    expect(adapter.requests[0].queryParameters, {'scope': 'past'});
    expect(adapter.requests[1].queryParameters, {'scope': 'past', 'page': 2});
    expect(adapter.requests[2].queryParameters, {'scope': 'upcoming'});
    expect(adapter.requests[0].path, '/bookings');
    expect(first.items.single.startingSoon, isTrue);
    expect(first.items.single.counselor.displayName, 'Aina');
    expect(first.items.single.startsAt.toUtc(), DateTime.utc(2026, 11, 3, 2));
    expect(first.lastPage, 3);
  });

  test('a state or a mode added later by the server does not break the list', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(200, {'data': [item(status: 'on_hold', mode: 'phone')], 'page': 1, 'last_page': 1}));
    final one = (await build(adapter).list(BookingsScope.upcoming)).items.single;

    expect(one.status, BookingState.unknown);
    expect(one.mode, SessionMode.unknown);
  });

  test('a counselor who no longer has a page has no id', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(200, {'data': [{...item(), 'counselor': {'id': null, 'display_name': ''}}], 'page': 1, 'last_page': 1}));

    expect((await build(adapter).list(BookingsScope.past)).items.single.counselor.id, isNull);
  });

  test('the detail reads the meeting (with its link only when available) and the full address', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(200, {
          'booking': {
            ...item(),
            'cancellation_hours': 24,
            'cancellation_deadline_at': null,
            'can_cancel': false,
            'reschedules_left': 2,
            'meeting': {'state': 'available', 'opens_at': '2026-11-03T09:45:00+08:00', 'closes_at': '2026-11-03T10:50:00+08:00', 'provider': 'Google Meet', 'url': 'https://meet.example/x'},
            'place': {'name': 'Klinik', 'address': 'No 1', 'city': 'Kuantan', 'state': 'Pahang', 'postcode': '25300', 'latitude': 3.8, 'longitude': 103.3},
          },
        }));

    final detail = await build(adapter).detail('01bk');

    expect(adapter.requests.single.path, '/bookings/01bk');
    expect(detail.meeting?.state, MeetingState.available);
    expect(detail.meeting?.url, 'https://meet.example/x');
    expect(detail.place?.address, 'No 1');
    expect(detail.place?.latitude, 3.8);
    expect(detail.canCancel, isFalse);
    expect(detail.cancellationDeadlineAt, isNull);
  });

  test('a meeting state added later is unknown, not a crash', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(200, {
          'booking': {...item(), 'cancellation_hours': 24, 'meeting': {'state': 'rescheduled', 'opens_at': '2026-11-03T09:45:00+08:00', 'closes_at': '2026-11-03T10:50:00+08:00'}},
        }));

    expect((await build(adapter).detail('x')).meeting?.state, MeetingState.unknown);
  });

  test('an id with odd characters cannot change the path', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(404, {'message': 'x', 'code': 'booking_not_found'}));

    await expectLater(build(adapter).detail('a/b?c'), throwsA(isA<NotFoundFailure>()));
    expect(adapter.requests.single.path, contains('a%2Fb%3Fc'));
  });

  test('cancelling posts to the own path of the booking and reads the cancelled booking back', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(200, {
          'booking': {...item(status: 'cancelled'), 'cancellation_hours': 24, 'can_cancel': false, 'reschedules_left': 2, 'meeting': null, 'place': null},
        }));

    final detail = await build(adapter).cancel('01bk');

    expect(adapter.requests.single.method, 'POST');
    expect(adapter.requests.single.path, '/bookings/01bk/cancel');
    expect(adapter.requests.single.data, anyOf(isNull, isEmpty));
    expect(detail.status, BookingState.cancelled);
    expect(detail.canCancel, isFalse);
    expect(detail.meeting, isNull);
  });

  test('a refusal to cancel keeps its code and the window the server named', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(409, {'message': 'x', 'code': 'cancellation_closed', 'cancellation_hours': 48}));

    await expectLater(
      build(adapter).cancel('01bk'),
      throwsA(isA<ConflictFailure>().having((f) => f.code, 'code', 'cancellation_closed').having((f) => f.body['cancellation_hours'], 'hours', 48)),
    );
  });

  test('rescheduling posts the new time with what the person saw, and reads the moved booking back', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(200, {
          'booking': {...item(), 'cancellation_hours': 24, 'can_cancel': true, 'can_reschedule': true, 'reschedules_left': 1, 'meeting': null, 'place': null},
        }));

    final detail = await build(adapter).reschedule('01bk', slotId: '01slot', expectedStartsAt: DateTime.utc(2026, 11, 5, 6), expectedDurationMinutes: 60, expectedMode: 'online');

    expect(adapter.requests.single.method, 'POST');
    expect(adapter.requests.single.path, '/bookings/01bk/reschedule');
    expect(adapter.requests.single.data, {
      'slot': '01slot',
      'expected_starts_at': '2026-11-05T06:00:00.000Z',
      'expected_duration_minutes': 60,
      'expected_mode': 'online',
    });
    expect(detail.canReschedule, isTrue);
    expect(detail.reschedulesLeft, 1);
  });

  test('a refusal to reschedule keeps its code and the window the server named', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(409, {'message': 'x', 'code': 'reschedule_closed', 'cancellation_hours': 48}));

    await expectLater(
      build(adapter).reschedule('01bk', slotId: 's', expectedStartsAt: DateTime.utc(2026, 11, 5), expectedDurationMinutes: 60, expectedMode: 'online'),
      throwsA(isA<ConflictFailure>().having((f) => f.code, 'code', 'reschedule_closed').having((f) => f.body['cancellation_hours'], 'hours', 48)),
    );
  });

  test('a detail without can_reschedule (an older server) is not offered the move', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(200, {
          'booking': {...item(), 'cancellation_hours': 24, 'can_cancel': true, 'reschedules_left': 2},
        }));

    expect((await build(adapter).detail('01bk')).canReschedule, isFalse);
  });
}
