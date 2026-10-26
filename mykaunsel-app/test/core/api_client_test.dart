import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mykaunsel/core/config/app_config.dart';
import 'package:mykaunsel/core/config/app_info.dart';
import 'package:mykaunsel/core/error/app_failure.dart';
import 'package:mykaunsel/core/network/api_client.dart';

import '../support/fakes.dart';

void main() {
  const config = AppConfig(flavor: Flavor.dev, apiBaseUrl: 'http://test.local');
  const info = AppInfo(version: '2.3.4', platform: 'ios');

  ApiClient build(FakeHttpAdapter adapter, {String? token, List<(AppFailure, bool)>? failures}) {
    final dio = Dio()..httpClientAdapter = adapter;

    return ApiClient(
      config: config,
      info: info,
      readToken: () async => token,
      onFailure: (failure, {required hadToken}) => failures?.add((failure, hadToken)),
      dio: dio,
    );
  }

  test('every request says which app version and platform it is, asks for JSON, and uses the /api/v1 address', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(200, {'ok': true}));

    final response = await build(adapter).get('/status');

    expect(response.statusCode, 200);
    expect(response.data, {'ok': true});
    final request = adapter.requests.single;
    expect(request.uri.toString(), 'http://test.local/api/v1/status');
    expect(request.headers['X-App-Version'], '2.3.4');
    expect(request.headers['X-App-Platform'], 'ios');
    expect(request.headers['Accept'], 'application/json');
    expect(request.headers.containsKey('Authorization'), isFalse);
  });

  test('the saved token is sent as a Bearer token', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(200, {}));

    await build(adapter, token: '5|mkp_abc').post('/auth/logout');

    expect(adapter.requests.single.headers['Authorization'], 'Bearer 5|mkp_abc');
    expect(adapter.requests.single.method, 'POST');
  });

  test('a 202 is a success, so the sign-in code step can be told apart', () async {
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(202, {'code': 'two_factor_required', 'challenge': 'abc'}));

    final response = await build(adapter).post('/auth/login', data: {'email': 'a@b.com'});

    expect(response.statusCode, 202);
    expect(response.data['challenge'], 'abc');
  });

  test('errors become AppFailure and the session is told, with whether a token was sent', () async {
    final failures = <(AppFailure, bool)>[];
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(503, {'message': 'Back soon.'}));

    await expectLater(build(adapter, token: 't', failures: failures).get('/auth/me'), throwsA(isA<MaintenanceFailure>()));

    expect(failures.single.$1, isA<MaintenanceFailure>());
    expect(failures.single.$2, isTrue);
  });

  test('a 401 without a token is reported as such (so a wrong sign-in is not "session expired")', () async {
    final failures = <(AppFailure, bool)>[];
    final adapter = FakeHttpAdapter((_) => FakeHttpAdapter.json(401, {'message': 'Unauthenticated.'}));

    await expectLater(build(adapter, failures: failures).get('/auth/me'), throwsA(isA<UnauthorizedFailure>()));

    expect(failures.single.$2, isFalse);
  });

  test('a body that is not a JSON object still gives an empty map, not a crash', () async {
    final adapter = FakeHttpAdapter((_) => ResponseBody.fromString('[]', 200, headers: {Headers.contentTypeHeader: ['application/json']}));

    expect((await build(adapter).get('/x')).data, isEmpty);
  });
}
