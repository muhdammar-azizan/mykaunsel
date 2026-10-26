import 'package:device_info_plus/device_info_plus.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'app.dart';
import 'core/config/app_config.dart';
import 'core/config/app_info.dart';
import 'core/error/crash_reporter.dart';
import 'core/providers.dart';
import 'core/storage/token_storage.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Swap this for the Firebase Crashlytics reporter once the Firebase project exists (see crash_reporter.dart).
  const CrashReporter reporter = NoopCrashReporter();
  wireCrashReporting(reporter);

  final AppConfig config;

  try {
    config = AppConfig.fromEnvironment();
  } on StateError catch (error) {
    // A build made without its settings: say so plainly instead of showing a blank screen.
    runApp(_ConfigErrorApp(message: error.message));

    return;
  }

  // Month and day names for the times shown on counselors (add 'ms' here when Bahasa Melayu is added).
  await initializeDateFormatting('en');

  final packageInfo = await PackageInfo.fromPlatform();
  final deviceName = await _deviceName();
  final preferences = await SharedPreferences.getInstance();

  final tokenStorage = SecureTokenStorage(
    secureStorage: const FlutterSecureStorage(
      // Never copied to a new phone through a backup (the matching key would not be there to open it).
      iOptions: IOSOptions(accessibility: KeychainAccessibility.first_unlock_this_device),
    ),
    preferences: preferences,
    reporter: reporter,
  );

  runApp(
    ProviderScope(
      overrides: [
        appConfigProvider.overrideWithValue(config),
        appInfoProvider.overrideWithValue(AppInfo(version: packageInfo.version, platform: _platformName(), deviceName: deviceName)),
        tokenStorageProvider.overrideWithValue(tokenStorage),
        crashReporterProvider.overrideWithValue(reporter),
      ],
      child: const MyKaunselApp(),
    ),
  );
}

String _platformName() => defaultTargetPlatform == TargetPlatform.iOS ? 'ios' : 'android';

/// The phone's MODEL ("Google Pixel 8", "iPhone"), never the name its owner gave it ("Siti's iPhone" is personal).
Future<String> _deviceName() async {
  try {
    final plugin = DeviceInfoPlugin();

    if (defaultTargetPlatform == TargetPlatform.iOS) {
      return (await plugin.iosInfo).model;
    }

    final android = await plugin.androidInfo;

    return '${android.manufacturer} ${android.model}'.trim();
  } catch (_) {
    return 'Phone';
  }
}

class _ConfigErrorApp extends StatelessWidget {
  const _ConfigErrorApp({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      home: Scaffold(
        body: SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Center(child: Text(message, textAlign: TextAlign.center)),
          ),
        ),
      ),
    );
  }
}
