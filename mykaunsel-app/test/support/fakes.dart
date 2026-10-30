import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/widgets.dart' show Text, Widget;
import 'package:flutter_riverpod/misc.dart' show Override;
import 'package:flutter_test/flutter_test.dart' show Finder, find;
import 'package:mykaunsel/core/config/app_config.dart';
import 'package:mykaunsel/core/config/app_info.dart';
import 'package:mykaunsel/core/providers.dart';
import 'package:mykaunsel/core/storage/token_storage.dart';
import 'package:mykaunsel/features/account/data/account_api.dart';
import 'package:mykaunsel/features/account/domain/signed_in_device.dart';
import 'package:mykaunsel/features/auth/data/auth_api.dart';
import 'package:mykaunsel/features/auth/domain/app_user.dart';
import 'package:mykaunsel/features/auth/domain/sign_in_result.dart';
import 'package:mykaunsel/features/booking/data/availability_api.dart';
import 'package:mykaunsel/features/booking/domain/availability.dart';
import 'package:mykaunsel/features/counselors/data/counselor_api.dart';
import 'package:mykaunsel/features/counselors/domain/counselor.dart';
import 'package:mykaunsel/features/counselors/domain/counselor_query.dart';
import 'package:mykaunsel/features/platform_status/data/platform_status_api.dart';
import 'package:mykaunsel/features/platform_status/domain/platform_status.dart';

const testUser = AppUser(id: 7, name: 'Siti Aminah', email: 'siti@example.com', emailVerified: true);

PlatformStatus status({bool maintenance = false, String? maintenanceMessage, bool updateRequired = false, String? storeUrl}) {
  return PlatformStatus(
    maintenance: MaintenanceStatus(isOn: maintenance, message: maintenanceMessage),
    app: AppVersionStatus(updateRequired: updateRequired, message: updateRequired ? 'Please update.' : null, storeUrl: storeUrl),
  );
}

class FakeTokenStorage implements TokenStorage {
  FakeTokenStorage([this.token]);

  String? token;
  int clears = 0;

  @override
  Future<String?> read() async => token;

  @override
  Future<void> write(String value) async => token = value;

  @override
  Future<void> clear() async {
    clears++;
    token = null;
  }
}

/// Each call runs [handler], so a test can return a status or throw a failure.
class FakePlatformStatusApi implements PlatformStatusApi {
  FakePlatformStatusApi(this.handler);

  Future<PlatformStatus> Function() handler;
  int calls = 0;

  @override
  Future<PlatformStatus> fetch() {
    calls++;

    return handler();
  }
}

class FakeAuthApi implements AuthApi {
  FakeAuthApi({Future<AppUser> Function()? me, Future<void> Function()? logout})
      : meHandler = me ?? (() async => testUser),
        logoutHandler = logout ?? (() async {});

  Future<AppUser> Function() meHandler;
  Future<void> Function() logoutHandler;
  int logoutCalls = 0;

  /// What a sign-in / second step / resend does. Defaults: signs in as [testUser].
  Future<SignInResult> Function(String email, String password) signInHandler =
      (email, password) async => const SignedInResult(token: '9|mkp_new', user: testUser);
  Future<SignedInResult> Function(String challenge, String? code, String? recoveryCode) twoFactorHandler =
      (challenge, code, recoveryCode) async => const SignedInResult(token: '9|mkp_new', user: testUser);
  Future<void> Function(String email) resendHandler = (email) async {};
  Future<void> Function(String name, String email, String password) registerHandler = (name, email, password) async {};
  Future<void> Function(String email) forgotPasswordHandler = (email) async {};
  Future<void> Function() logoutAllHandler = () async {};

  final signInCalls = <({String email, String password, String deviceName})>[];
  final twoFactorCalls = <({String challenge, String? code, String? recoveryCode})>[];
  final resendCalls = <String>[];
  final registerCalls = <({String name, String email, String password})>[];
  final forgotPasswordCalls = <String>[];
  int logoutAllCalls = 0;

  @override
  Future<void> register({required String name, required String email, required String password}) {
    registerCalls.add((name: name, email: email, password: password));

    return registerHandler(name, email, password);
  }

  @override
  Future<void> forgotPassword(String email) {
    forgotPasswordCalls.add(email);

    return forgotPasswordHandler(email);
  }

  @override
  Future<void> logoutAll() {
    logoutAllCalls++;

    return logoutAllHandler();
  }

  @override
  Future<SignInResult> signIn({required String email, required String password, required String deviceName}) {
    signInCalls.add((email: email, password: password, deviceName: deviceName));

    return signInHandler(email, password);
  }

  @override
  Future<SignedInResult> twoFactor({required String challenge, String? code, String? recoveryCode, required String deviceName}) {
    twoFactorCalls.add((challenge: challenge, code: code, recoveryCode: recoveryCode));

    return twoFactorHandler(challenge, code, recoveryCode);
  }

  @override
  Future<void> resendVerification(String email) {
    resendCalls.add(email);

    return resendHandler(email);
  }

  @override
  Future<AppUser> me() => meHandler();

  @override
  Future<void> logout() {
    logoutCalls++;

    return logoutHandler();
  }
}

/// 2 Nov 2026, 09:00 in Malaysia (01:00 UTC): "today" for every test that shows a day.
final testNow = DateTime.utc(2026, 11, 2, 1);

/// A free slot `days` after [testNow]'s day at `hour`:`minute` Malaysian time (the way the server sends it).
CounselorSlot aSlot({int days = 1, int hour = 10, int minute = 0, SessionMode mode = SessionMode.online, String? city, String? state, CounselorContext? context}) {
  return CounselorSlot(
    startsAt: DateTime.utc(2026, 11, 2 + days, hour - 8, minute),
    durationMinutes: 60,
    sessionMode: mode,
    city: city,
    state: state,
    context: context ?? const CounselorContext(type: ContextType.independent),
  );
}

Counselor aCounselor({
  String id = 'c1',
  String name = 'Kaunselor Aina Syazwani',
  List<String> specializations = const ['Kemurungan', 'Stres'],
  int? specializationsCount,
  List<String> languages = const ['English', 'Bahasa Malaysia'],
  List<SessionMode> modes = const [SessionMode.online],
  List<CounselorLocation> locations = const [],
  List<CounselorContext> contexts = const [CounselorContext(type: ContextType.independent)],
  CounselorSlot? next,
  bool withNext = true,
  bool? acceptsRequests,
  List<CounselorSlot> upcoming = const [],
}) {
  return Counselor(
    id: id,
    name: name,
    contexts: contexts,
    specializations: specializations,
    specializationsCount: specializationsCount ?? specializations.length,
    languages: languages,
    sessionModes: modes,
    locations: locations,
    nextAvailable: next ?? (withNext ? aSlot() : null),
    acceptsRequests: acceptsRequests,
    upcomingSlots: upcoming,
  );
}

CounselorPage aPage(List<Counselor> items, {int page = 1, int lastPage = 1, int? total}) =>
    CounselorPage(items: items, page: page, lastPage: lastPage, total: total ?? items.length);

class FakeCounselorApi implements CounselorApi {
  Future<CounselorPage> Function(CounselorQuery query, int page) searchHandler = (query, page) async => aPage([aCounselor()]);
  Future<CounselorFilterOptions> Function() filtersHandler = () async => const CounselorFilterOptions(
        specializations: ['Kemurungan', 'Stres', 'Trauma'],
        languages: ['English', 'Bahasa Malaysia', 'Mandarin'],
        sessionModes: [SessionMode.online, SessionMode.physical],
        states: ['Pahang'],
      );
  Future<Counselor> Function(String id) detailHandler = (id) async => aCounselor(id: id, acceptsRequests: true, upcoming: [aSlot()]);

  final searches = <({CounselorQuery query, int page})>[];
  final details = <String>[];
  int filterCalls = 0;

  @override
  Future<CounselorPage> search(CounselorQuery query, {int page = 1}) {
    searches.add((query: query, page: page));

    return searchHandler(query, page);
  }

  @override
  Future<CounselorFilterOptions> filters() {
    filterCalls++;

    return filtersHandler();
  }

  @override
  Future<Counselor> detail(String id) {
    details.add(id);

    return detailHandler(id);
  }
}

class FakeAccountApi implements AccountApi {
  Future<AppUser> Function(String name, String timezone) updateProfileHandler =
      (name, timezone) async => AppUser(id: testUser.id, name: name, email: testUser.email, emailVerified: true, timezone: timezone);
  Future<void> Function(String current, String next) changePasswordHandler = (current, next) async {};
  Future<List<SignedInDevice>> Function() devicesHandler = () async => [
        const SignedInDevice(id: 1, name: 'Google Pixel 8', current: true),
        SignedInDevice(id: 2, name: 'Old Tablet', lastUsedAt: testNow.subtract(const Duration(hours: 5))),
      ];
  Future<void> Function(int id, String current) revokeHandler = (id, current) async {};

  final profileCalls = <({String name, String timezone})>[];
  final passwordCalls = <({String current, String next})>[];
  final revokeCalls = <({int id, String current})>[];
  int deviceCalls = 0;

  @override
  Future<AppUser> updateProfile({required String name, required String timezone}) {
    profileCalls.add((name: name, timezone: timezone));

    return updateProfileHandler(name, timezone);
  }

  @override
  Future<void> changePassword({required String currentPassword, required String newPassword}) {
    passwordCalls.add((current: currentPassword, next: newPassword));

    return changePasswordHandler(currentPassword, newPassword);
  }

  @override
  Future<List<SignedInDevice>> devices() {
    deviceCalls++;

    return devicesHandler();
  }

  @override
  Future<void> revokeDevice(int id, {required String currentPassword}) {
    revokeCalls.add((id: id, current: currentPassword));

    return revokeHandler(id, currentPassword);
  }
}

class FakeAvailabilityApi implements AvailabilityApi {
  final calls = <({String id, String? from})>[];

  Future<Availability> Function(String id, String? from) handler = (id, from) async => throw StateError('no availability handler set');

  @override
  Future<Availability> fetch(String counselorId, {String? from}) {
    calls.add((id: counselorId, from: from));

    return handler(counselorId, from);
  }
}

/// Finds text however the phone writes the space before AM/PM (newer intl uses a narrow no-break space).
Finder findText(String text) => find.byWidgetPredicate((Widget w) => w is Text && (w.data ?? '').replaceAll(' ', ' ') == text, description: 'text "$text"');

Finder findTextContaining(String text) => find.byWidgetPredicate((Widget w) => w is Text && (w.data ?? '').replaceAll(' ', ' ').contains(text), description: 'text containing "$text"');

/// Everything a test needs, wired the way main.dart wires the real ones.
List<Override> testOverrides({
  FakePlatformStatusApi? statusApi,
  FakeAuthApi? authApi,
  FakeTokenStorage? storage,
  FakeCounselorApi? counselorApi,
  FakeAccountApi? accountApi,
  FakeAvailabilityApi? availabilityApi,
}) {
  return [
    appConfigProvider.overrideWithValue(const AppConfig(flavor: Flavor.dev, apiBaseUrl: 'http://test.local')),
    appInfoProvider.overrideWithValue(const AppInfo(version: '1.0.0', platform: 'android')),
    tokenStorageProvider.overrideWithValue(storage ?? FakeTokenStorage()),
    platformStatusApiProvider.overrideWithValue(statusApi ?? FakePlatformStatusApi(() async => status())),
    authApiProvider.overrideWithValue(authApi ?? FakeAuthApi()),
    counselorApiProvider.overrideWithValue(counselorApi ?? FakeCounselorApi()),
    accountApiProvider.overrideWithValue(accountApi ?? FakeAccountApi()),
    availabilityApiProvider.overrideWithValue(availabilityApi ?? FakeAvailabilityApi()),
    clockProvider.overrideWithValue(() => testNow),
  ];
}

/// A Dio adapter that answers from a function instead of the network.
class FakeHttpAdapter implements HttpClientAdapter {
  FakeHttpAdapter(this.respond);

  final ResponseBody Function(RequestOptions options) respond;
  final List<RequestOptions> requests = [];

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    requests.add(options);

    return respond(options);
  }

  @override
  void close({bool force = false}) {}

  static ResponseBody json(int status, Object body, {Map<String, List<String>> headers = const {}}) {
    return ResponseBody.fromString(
      jsonEncode(body),
      status,
      headers: {
        Headers.contentTypeHeader: ['application/json'],
        ...headers,
      },
    );
  }
}
