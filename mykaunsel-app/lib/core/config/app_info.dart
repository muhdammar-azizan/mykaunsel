/// Facts about the installed app that the server needs: which version (so very old versions can be told to update)
/// and which platform (so they are sent to the right store).
class AppInfo {
  const AppInfo({required this.version, required this.platform, this.deviceName = 'Phone'});

  /// `major.minor.patch`, for example `1.0.0` (the build number is left out on purpose).
  final String version;

  /// `android` or `ios`.
  final String platform;

  /// Names this phone in the server's list of signed-in devices, for example "Google Pixel 8". It is the MODEL, never a
  /// name the owner gave the phone ("Siti's iPhone" is personal).
  final String deviceName;
}
