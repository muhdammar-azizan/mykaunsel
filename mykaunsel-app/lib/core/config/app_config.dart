/// Which build this is. dev talks to a test server, prod to the real one.
enum Flavor { dev, prod }

/// Where the app talks to. Set at build time (`--dart-define-from-file=env/dev.json`), never edited in code, so a
/// release build can not accidentally point at a developer's laptop.
class AppConfig {
  const AppConfig({required this.flavor, required this.apiBaseUrl});

  /// Reads the `--dart-define` values. Throws a clear message when they are missing or unsafe.
  factory AppConfig.fromEnvironment() {
    const flavorName = String.fromEnvironment('FLAVOR');
    const baseUrl = String.fromEnvironment('API_BASE_URL');

    return AppConfig.parse(flavorName: flavorName, apiBaseUrl: baseUrl);
  }

  factory AppConfig.parse({required String flavorName, required String apiBaseUrl}) {
    final flavor = Flavor.values.where((f) => f.name == flavorName).firstOrNull;

    if (flavor == null) {
      throw StateError(
        'FLAVOR must be "dev" or "prod" (got "$flavorName"). '
        'Run with --dart-define-from-file=env/dev.json (see README).',
      );
    }

    final url = apiBaseUrl.trim().replaceAll(RegExp(r'/+$'), '');
    final uri = Uri.tryParse(url);

    if (uri == null || !uri.hasScheme || uri.host.isEmpty) {
      throw StateError('API_BASE_URL is missing or not a valid address (got "$apiBaseUrl").');
    }

    // Real users' sign-in details must never travel over plain http.
    if (flavor == Flavor.prod && uri.scheme != 'https') {
      throw StateError('A prod build must use https (got "$apiBaseUrl").');
    }

    return AppConfig(flavor: flavor, apiBaseUrl: url);
  }

  final Flavor flavor;

  /// Without a trailing slash, for example `https://api.example.com`.
  final String apiBaseUrl;

  bool get isDev => flavor == Flavor.dev;

  /// Every API call goes under /api/v1.
  String get apiUrl => '$apiBaseUrl/api/v1';
}
