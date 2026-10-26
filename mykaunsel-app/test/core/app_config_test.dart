import 'package:flutter_test/flutter_test.dart';
import 'package:mykaunsel/core/config/app_config.dart';

void main() {
  test('reads flavor and address, and removes a trailing slash', () {
    final config = AppConfig.parse(flavorName: 'dev', apiBaseUrl: 'http://10.0.2.2:8000/');

    expect(config.flavor, Flavor.dev);
    expect(config.apiBaseUrl, 'http://10.0.2.2:8000');
    expect(config.apiUrl, 'http://10.0.2.2:8000/api/v1');
  });

  test('a missing or unknown flavor is refused with a clear message', () {
    expect(() => AppConfig.parse(flavorName: '', apiBaseUrl: 'https://x.my'), throwsA(isA<StateError>()));
    expect(() => AppConfig.parse(flavorName: 'staging', apiBaseUrl: 'https://x.my'), throwsA(isA<StateError>()));
  });

  test('a missing or broken address is refused', () {
    expect(() => AppConfig.parse(flavorName: 'dev', apiBaseUrl: ''), throwsA(isA<StateError>()));
    expect(() => AppConfig.parse(flavorName: 'dev', apiBaseUrl: 'not a url'), throwsA(isA<StateError>()));
  });

  test('a prod build can never use plain http', () {
    expect(() => AppConfig.parse(flavorName: 'prod', apiBaseUrl: 'http://api.mykaunsel.com.my'), throwsA(isA<StateError>()));
    expect(AppConfig.parse(flavorName: 'prod', apiBaseUrl: 'https://api.mykaunsel.com.my').isDev, isFalse);
  });
}
