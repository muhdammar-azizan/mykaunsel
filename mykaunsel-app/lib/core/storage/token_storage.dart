import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../error/crash_reporter.dart';

/// Holds the sign-in token. Everything else about the person is fetched from the server again.
abstract class TokenStorage {
  Future<String?> read();

  Future<void> write(String token);

  Future<void> clear();
}

/// Keeps the token in the phone's secure store (Android Keystore / iOS Keychain), with two real-world fixes:
///
///  * **iOS keeps Keychain data after the app is deleted.** A reinstall would silently sign the previous person back
///    in. A marker in ordinary preferences (which ARE deleted with the app) tells us this is a fresh install, and
///    the old token is wiped before it is ever used.
///  * **Android can end up with data it can no longer decrypt** (restored or changed lock screen). Reading then throws;
///    we wipe the store and carry on signed out instead of crashing at start-up.
///
/// The token is kept in memory after the first read, so requests do not wait for the secure store.
class SecureTokenStorage implements TokenStorage {
  SecureTokenStorage({
    required FlutterSecureStorage secureStorage,
    required SharedPreferences preferences,
    CrashReporter reporter = const NoopCrashReporter(),
  })  : _secure = secureStorage,
        _prefs = preferences,
        _reporter = reporter;

  static const _tokenKey = 'auth_token';
  static const _installMarkerKey = 'install_marker_v1';

  final FlutterSecureStorage _secure;
  final SharedPreferences _prefs;
  final CrashReporter _reporter;

  String? _cached;
  bool _loaded = false;
  bool _installChecked = false;

  @override
  Future<String?> read() async {
    if (_loaded) {
      return _cached;
    }

    await _wipeIfFreshInstall();

    try {
      _cached = await _secure.read(key: _tokenKey);
    } catch (error, stack) {
      _reporter.report(error, stack);
      await _deleteEverything();
      _cached = null;
    }

    _loaded = true;

    return _cached;
  }

  @override
  Future<void> write(String token) async {
    await _wipeIfFreshInstall();
    await _secure.write(key: _tokenKey, value: token);

    _cached = token;
    _loaded = true;
  }

  @override
  Future<void> clear() async {
    _cached = null;
    _loaded = true;

    await _deleteEverything();
  }

  Future<void> _wipeIfFreshInstall() async {
    if (_installChecked) {
      return;
    }

    if (_prefs.getBool(_installMarkerKey) != true) {
      await _deleteEverything();
      await _prefs.setBool(_installMarkerKey, true);
    }

    _installChecked = true;
  }

  Future<void> _deleteEverything() async {
    try {
      await _secure.deleteAll();
    } catch (error, stack) {
      _reporter.report(error, stack);
    }
  }
}
