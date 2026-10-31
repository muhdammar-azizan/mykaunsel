import 'package:shared_preferences/shared_preferences.dart';

/// Remembers what the person has already been shown or has hidden on this phone: the first-time introduction (seen or skipped) and the hint about a
/// reference code on Home. Ordinary preferences, deleted with the app, so a reinstall shows them again.
abstract class OnboardingStore {
  bool get seen;

  Future<void> markSeen();

  /// The "Have a code from your organization?" card on Home was hidden by the person.
  bool get orgHintDismissed;

  Future<void> dismissOrgHint();
}

class PrefsOnboardingStore implements OnboardingStore {
  PrefsOnboardingStore(this._prefs);

  static const _key = 'onboarding_seen_v1';
  static const _orgHintKey = 'org_hint_dismissed_v1';

  final SharedPreferences _prefs;

  @override
  bool get seen => _prefs.getBool(_key) ?? false;

  @override
  Future<void> markSeen() async {
    await _prefs.setBool(_key, true);
  }

  @override
  bool get orgHintDismissed => _prefs.getBool(_orgHintKey) ?? false;

  @override
  Future<void> dismissOrgHint() async {
    await _prefs.setBool(_orgHintKey, true);
  }
}
