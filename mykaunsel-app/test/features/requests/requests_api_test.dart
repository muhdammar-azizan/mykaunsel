import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mykaunsel/core/config/app_config.dart';
import 'package:mykaunsel/core/config/app_info.dart';
import 'package:mykaunsel/core/error/app_failure.dart';
import 'package:mykaunsel/core/network/api_client.dart';
import 'package:mykaunsel/features/counselors/domain/counselor.dart';
import 'package:mykaunsel/features/requests/data/requests_api.dart';
import 'package:mykaunsel/features/requests/domain/time_request.dart';

import '../../support/fakes.dart';

/// The real HttpRequestsApi against answers shaped like the server's (docs/AVAILABILITY.md, "Time requests").
void main() {
  HttpRequestsApi build(FakeHttpAdapter adapter) {
    final client = ApiClient(
      config: const AppConfig(flavor: Flavor.dev, apiBaseUrl: 'http://test.local'),
      info: const AppInfo(version: '1.0.0', platform: 'android'),
      readToken: () async => 'tok',
      onFailure: (_, {required hadToken}) {},
      dio: Dio()..httpClientAdapter = adapter,
    );

    return HttpRequestsApi(client);
  }

  Map<String, dynamic> request({String status = 'pending'}) => {
        'id': '01rq',
        'status': status,
        'counselor': {'id': '01c', 'display_name': 'Aina'},
        'starts_at': '2026-11-05T10:00:00+08:00',
        'ends_at': '2026-11-05T11:00:00+08:00',
        'duration_minutes': 60,
        'mode': 'online',
        'location': null,
        'message': 'About exams',
        'response_reason': null,
        'expires_at': '2026-11-04T09:00:00+08:00',
        'responded_at': null,
        'booking_id': null,
      };

  test('the working hours read the lines, the window and how long the counselor has to answer', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(200, {
          'counselor': {'id': '01c', 'display_name': 'Aina'},
          'accepts_requests': true,
          'hours': [
            {'weekday': 4, 'from': '10:00', 'to': '13:00', 'duration_minutes': 60, 'mode': 'online', 'location': null},
            {'weekday': 1, 'from': '14:00', 'to': '16:00', 'duration_minutes': 30, 'mode': 'physical', 'location': {'name': 'Klinik', 'city': 'Kuantan'}},
          ],
          'booking_window': {'earliest_start': '2026-11-02T11:00:00+08:00', 'last_date': '2026-12-02', 'min_lead_hours': 2},
          'answer_within_hours': 48,
        }));

    final hours = await build(adapter).workingHours('01c');

    expect(adapter.requests.single.path, '/counselors/01c/working-hours');
    expect(hours.acceptsRequests, isTrue);
    expect(hours.hours.length, 2);
    expect(hours.hours.first.fromMinutes, 600);
    expect(hours.hours.last.location?.city, 'Kuantan');
    expect(hours.bookingWindow.lastDate, '2026-12-02');
    expect(hours.answerWithinHours, 48);
  });

  test('a mode or a state added later by the server does not break', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(200, {'request': {...request(status: 'on_hold'), 'mode': 'phone'}}));

    final read = await build(adapter).detail('01rq');

    expect(read.status, RequestState.unknown);
    expect(read.mode, SessionMode.unknown);
  });

  test('asking posts the date, the time and the trimmed note, and reads the request back', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(201, {'request': request()}));

    final made = await build(adapter).send('01c', date: '2026-11-05', time: '10:00', message: '  About exams  ');

    expect(adapter.requests.single.method, 'POST');
    expect(adapter.requests.single.path, '/counselors/01c/requests');
    expect(adapter.requests.single.data, {'date': '2026-11-05', 'time': '10:00', 'message': 'About exams'});
    expect(made.status, RequestState.pending);
    expect(made.startsAt.toUtc(), DateTime.utc(2026, 11, 5, 2));
    expect(made.message, 'About exams');
  });

  test('an empty note is not sent', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(201, {'request': request()}));

    await build(adapter).send('01c', date: '2026-11-05', time: '10:00', message: '   ');

    expect(adapter.requests.single.data, {'date': '2026-11-05', 'time': '10:00'});
  });

  test('a refusal keeps its code', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(409, {'message': 'x', 'code': 'request_limit_reached', 'max_pending_requests': 3}));

    await expectLater(
      build(adapter).send('01c', date: '2026-11-05', time: '10:00'),
      throwsA(isA<ConflictFailure>().having((f) => f.code, 'code', 'request_limit_reached').having((f) => f.body['max_pending_requests'], 'max', 3)),
    );
  });

  test('a time outside the hours is a validation failure', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(422, {'message': 'x', 'code': 'request_time_invalid'}));

    await expectLater(build(adapter).send('01c', date: '2026-11-05', time: '10:00'), throwsA(isA<ValidationFailure>()));
  });

  test('the list asks for a page only after the first, and reads the page numbers', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(200, {'data': [request()], 'page': 2, 'last_page': 3}));
    final api = build(adapter);

    await api.list();
    final second = await api.list(page: 2);

    expect(adapter.requests[0].path, '/requests');
    expect(adapter.requests[0].queryParameters, isEmpty);
    expect(adapter.requests[1].queryParameters, {'page': 2});
    expect(second.items.single.id, '01rq');
    expect(second.lastPage, 3);
  });

  test('withdrawing posts to the own path of the request', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(200, {'request': request(status: 'withdrawn')}));

    final read = await build(adapter).withdraw('01rq');

    expect(adapter.requests.single.method, 'POST');
    expect(adapter.requests.single.path, '/requests/01rq/withdraw');
    expect(read.status, RequestState.withdrawn);
  });

  test('an id with odd characters cannot change the path', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(404, {'message': 'x', 'code': 'request_not_found'}));

    await expectLater(build(adapter).detail('a/b?c'), throwsA(isA<NotFoundFailure>()));
    expect(adapter.requests.single.path, contains('a%2Fb%3Fc'));
  });
}
