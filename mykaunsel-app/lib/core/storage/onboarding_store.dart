import 'package:shared_preferences/shared_preferences.dart';

/// Remembers that the first-time introduction has been seen (or skipped). Ordinary preferences, deleted with the app, so a reinstall shows it again.
abstract class OnboardingStore {
  bool get seen;

  Future<void> markSeen();
}

class PrefsOnboardingStore implements OnboardingStore {
  PrefsOnboardingStore(this._prefs);

  static const _key = 'onboarding_seen_v1';

  final SharedPreferences _prefs;

  @override
  bool get seen => _prefs.getBool(_key) ?? false;

  @override
  Future<void> markSeen() async {
    await _prefs.setBool(_key, true);
  }
}
