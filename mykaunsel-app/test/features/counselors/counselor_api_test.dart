import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mykaunsel/core/config/app_config.dart';
import 'package:mykaunsel/core/config/app_info.dart';
import 'package:mykaunsel/core/error/app_failure.dart';
import 'package:mykaunsel/core/network/api_client.dart';
import 'package:mykaunsel/features/counselors/data/counselor_api.dart';
import 'package:mykaunsel/features/counselors/domain/counselor.dart';
import 'package:mykaunsel/features/counselors/domain/counselor_query.dart';

import '../../support/fakes.dart';

void main() {
  HttpCounselorApi build(FakeHttpAdapter adapter) {
    return HttpCounselorApi(ApiClient(
      config: const AppConfig(flavor: Flavor.dev, apiBaseUrl: 'http://test.local'),
      info: const AppInfo(version: '1.0.0', platform: 'android'),
      readToken: () async => '5|mkp_abc',
      onFailure: (_, {required hadToken}) {},
      dio: Dio()..httpClientAdapter = adapter,
    ));
  }

  final card = {
    'id': '01abc',
    'name': 'Aina',
    'verified': true,
    'contexts': [],
    'specializations': ['Stres'],
    'specializations_count': 1,
    'languages': ['English'],
    'session_modes': ['online'],
    'locations': [],
    'next_available': null,
  };

  test('search sends only what is set, as query parameters, with the token', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(200, {'data': [card], 'meta': {'page': 1, 'per_page': 20, 'total': 1, 'last_page': 1}}));

    final page = await build(adapter).search(const CounselorQuery(q: 'ain', mode: SessionMode.online, state: 'Pahang'));

    expect(adapter.requests.single.path, '/counselors');
    expect(adapter.requests.single.queryParameters, {'q': 'ain', 'mode': 'online', 'state': 'Pahang'});
    expect(adapter.requests.single.headers['Authorization'], 'Bearer 5|mkp_abc');
    expect(page.items.single.name, 'Aina');
    expect((page.page, page.lastPage, page.total, page.hasMore), (1, 1, 1, false));
  });

  test('later pages ask for the page number, and the meta says whether there is more', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(200, {'data': [card], 'meta': {'page': 2, 'per_page': 20, 'total': 45, 'last_page': 3}}));

    final page = await build(adapter).search(const CounselorQuery(), page: 2);

    expect(adapter.requests.single.queryParameters, {'page': 2});
    expect(page.hasMore, isTrue);
    expect(page.total, 45);
  });

  test('filters and detail are read from the data key', () async {
    final adapter = FakeHttpAdapter((o) => o.path == '/counselors/filters'
        ? FakeHttpAdapter.json(200, {'data': {'specializations': ['A'], 'languages': ['B'], 'session_modes': ['online'], 'states': ['C']}})
        : FakeHttpAdapter.json(200, {'data': {...card, 'accepts_requests': true, 'upcoming_slots': []}}));
    final api = build(adapter);

    final options = await api.filters();
    final counselor = await api.detail('01abc');

    expect(options.specializations, ['A']);
    expect(options.states, ['C']);
    expect(counselor.acceptsRequests, isTrue);
    expect(adapter.requests.last.path, '/counselors/01abc');
  });

  test('a counselor who is not there, or may not be seen, is NotFoundFailure', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(404, {'message': 'Not Found'}));

    await expectLater(build(adapter).detail('01abc'), throwsA(isA<NotFoundFailure>()));
  });

  test('an id with odd characters can not change the address', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(404, {}));

    await expectLater(build(adapter).detail('../auth/logout-all'), throwsA(isA<NotFoundFailure>()));
    expect(adapter.requests.single.path, '/counselors/..%2Fauth%2Flogout-all');
  });

  test('rate limit and maintenance arrive as their own failures', () async {
    final limited = FakeHttpAdapter((_) => FakeHttpAdapter.json(429, {'message': 'Too many'}, headers: {'retry-after': ['30']}));
    await expectLater(build(limited).search(const CounselorQuery()), throwsA(isA<RateLimitedFailure>().having((f) => f.retryAfterSeconds, 'wait', 30)));

    final down = FakeHttpAdapter((_) => FakeHttpAdapter.json(503, {'message': 'Back soon'}));
    await expectLater(build(down).filters(), throwsA(isA<MaintenanceFailure>()));
  });
}
