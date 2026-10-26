import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:mykaunsel/core/error/crash_reporter.dart';
import 'package:mykaunsel/core/storage/token_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';

class MockSecureStorage extends Mock implements FlutterSecureStorage {}

class RecordingReporter implements CrashReporter {
  final reported = <Object>[];

  @override
  void report(Object error, StackTrace? stack, {bool fatal = false}) => reported.add(error);
}

void main() {
  late MockSecureStorage secure;

  Future<SecureTokenStorage> build({bool installedBefore = true, RecordingReporter? reporter}) async {
    SharedPreferences.setMockInitialValues({if (installedBefore) 'install_marker_v1': true});
    final prefs = await SharedPreferences.getInstance();

    return SecureTokenStorage(secureStorage: secure, preferences: prefs, reporter: reporter ?? RecordingReporter());
  }

  setUp(() {
    secure = MockSecureStorage();
    when(() => secure.read(key: any(named: 'key'))).thenAnswer((_) async => null);
    when(() => secure.write(key: any(named: 'key'), value: any(named: 'value'))).thenAnswer((_) async {});
    when(() => secure.deleteAll()).thenAnswer((_) async {});
  });

  test('reads the saved token once and then answers from memory', () async {
    when(() => secure.read(key: 'auth_token')).thenAnswer((_) async => '5|mkp_abc');
    final storage = await build();

    expect(await storage.read(), '5|mkp_abc');
    expect(await storage.read(), '5|mkp_abc');
    verify(() => secure.read(key: 'auth_token')).called(1);
  });

  test('a fresh install wipes whatever an earlier install left in the Keychain, before it can be used', () async {
    when(() => secure.read(key: 'auth_token')).thenAnswer((_) async => 'leftover-from-a-previous-install');
    final storage = await build(installedBefore: false);

    await storage.read();

    verify(() => secure.deleteAll()).called(1);
    final prefs = await SharedPreferences.getInstance();
    expect(prefs.getBool('install_marker_v1'), isTrue);
  });

  test('the fresh-install wipe happens only once', () async {
    final storage = await build(installedBefore: false);

    await storage.read();
    await storage.clear();
    await storage.write('t');

    // once for the fresh install + once for clear()
    verify(() => secure.deleteAll()).called(2);
  });

  test('a token written on a fresh install is not wiped by the install check', () async {
    final storage = await build(installedBefore: false);

    await storage.write('5|mkp_new');

    expect(await storage.read(), '5|mkp_new');
    verify(() => secure.write(key: 'auth_token', value: '5|mkp_new')).called(1);
  });

  test('data that cannot be decrypted is wiped and reported, and the app carries on signed out', () async {
    when(() => secure.read(key: 'auth_token')).thenThrow(Exception('BAD_DECRYPT'));
    final reporter = RecordingReporter();
    final storage = await build(reporter: reporter);

    expect(await storage.read(), isNull);

    verify(() => secure.deleteAll()).called(1);
    expect(reporter.reported, hasLength(1));
  });

  test('clear removes the token from memory and from the store', () async {
    final storage = await build();
    await storage.write('t');

    await storage.clear();

    expect(await storage.read(), isNull);
    verify(() => secure.deleteAll()).called(1);
  });

  test('a store that cannot even be wiped does not crash the app', () async {
    when(() => secure.deleteAll()).thenThrow(Exception('locked'));
    final storage = await build();

    await expectLater(storage.clear(), completes);
  });
}
