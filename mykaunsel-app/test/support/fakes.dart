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
import 'package:mykaunsel/features/booking/data/booking_api.dart';
import 'package:mykaunsel/features/booking/domain/availability.dart';
import 'package:mykaunsel/features/booking/domain/booking_preview.dart';
import 'package:mykaunsel/features/bookings/data/my_bookings_api.dart';
import 'package:mykaunsel/features/chat/data/chat_api.dart';
import 'package:mykaunsel/features/chat/domain/chat.dart';
import 'package:mykaunsel/features/requests/data/requests_api.dart';
import 'package:mykaunsel/features/requests/domain/time_request.dart';
import 'package:mykaunsel/features/bookings/domain/my_booking.dart';
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
  Future<AppUser> Function(bool consent) aiConsentHandler = (consent) async => AppUser(id: testUser.id, name: testUser.name, email: testUser.email, emailVerified: true, aiConsent: consent);
  final consentCalls = <bool>[];
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
  Future<AppUser> setAiConsent(bool consent) {
    consentCalls.add(consent);

    return aiConsentHandler(consent);
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

class FakeBookingApi implements BookingApi {
  final previews = <String>[];
  final creates = <({String key, String slot, DateTime startsAt, int minutes, String mode})>[];

  Future<BookingPreview> Function(String slot) previewHandler = (slot) async => throw StateError('no preview handler set');
  Future<CreatedBooking> Function(String key) createHandler = (key) async => throw StateError('no create handler set');

  @override
  Future<BookingPreview> preview(String slotId) {
    previews.add(slotId);

    return previewHandler(slotId);
  }

  @override
  Future<CreatedBooking> create({required String idempotencyKey, required String slotId, required DateTime expectedStartsAt, required int expectedDurationMinutes, required String expectedMode}) {
    creates.add((key: idempotencyKey, slot: slotId, startsAt: expectedStartsAt, minutes: expectedDurationMinutes, mode: expectedMode));

    return createHandler(idempotencyKey);
  }
}

class FakeMyBookingsApi implements MyBookingsApi {
  final lists = <({BookingsScope scope, int page})>[];
  final details = <String>[];
  final cancels = <String>[];
  final reschedules = <({String id, String slotId, DateTime startsAt, int minutes, String mode})>[];

  Future<MyBookingDetail> Function(String id) cancelHandler = (id) async => aBookingDetail(id: id, status: BookingState.cancelled, canCancel: false, withDeadline: false);
  Future<MyBookingDetail> Function(String id, String slotId) rescheduleHandler = (id, slotId) async => aBookingDetail(id: id, days: 3, reschedulesLeft: 1);
  Future<BookingsPage> Function(BookingsScope scope, int page) listHandler = (scope, page) async => const BookingsPage(items: [], page: 1, lastPage: 1);
  Future<MyBookingDetail> Function(String id) detailHandler = (id) async => aBookingDetail(id: id);

  @override
  Future<BookingsPage> list(BookingsScope scope, {int page = 1}) {
    lists.add((scope: scope, page: page));

    return listHandler(scope, page);
  }

  @override
  Future<MyBookingDetail> detail(String id) {
    details.add(id);

    return detailHandler(id);
  }

  @override
  Future<MyBookingDetail> cancel(String id) {
    cancels.add(id);

    return cancelHandler(id);
  }

  @override
  Future<MyBookingDetail> reschedule(
    String id, {
    required String slotId,
    required DateTime expectedStartsAt,
    required int expectedDurationMinutes,
    required String expectedMode,
  }) {
    reschedules.add((id: id, slotId: slotId, startsAt: expectedStartsAt, minutes: expectedDurationMinutes, mode: expectedMode));

    return rescheduleHandler(id, slotId);
  }
}

/// One line of the list: a session `days` after [testNow]'s day at `hour`:00 Malaysian time.
MyBooking aMyBooking({
  String id = 'bk1',
  BookingState status = BookingState.confirmed,
  int days = 1,
  int hour = 10,
  int minutes = 50,
  SessionMode mode = SessionMode.online,
  SlotLocation? location,
  String counselorName = 'Kaunselor Aina Syazwani',
  String? counselorId = 'c1',
  bool startingSoon = false,
}) {
  final start = DateTime.utc(2026, 11, 2 + days, hour - 8);

  return MyBooking(
    id: id,
    status: status,
    startingSoon: startingSoon,
    startsAt: start,
    endsAt: start.add(Duration(minutes: minutes)),
    durationMinutes: minutes,
    mode: mode,
    counselor: BookingCounselorRef(id: counselorId, displayName: counselorName),
    location: location,
  );
}

MyBookingDetail aBookingDetail({
  String id = 'bk1',
  BookingState status = BookingState.confirmed,
  int days = 1,
  int hour = 10,
  int minutes = 50,
  SessionMode mode = SessionMode.online,
  SlotLocation? location,
  MeetingInfo? meeting,
  BookingPlace? place,
  bool canCancel = true,
  bool canReschedule = true,
  int reschedulesLeft = 2,
  bool withDeadline = true,
}) {
  final start = DateTime.utc(2026, 11, 2 + days, hour - 8);

  return MyBookingDetail(
    id: id,
    status: status,
    startsAt: start,
    endsAt: start.add(Duration(minutes: minutes)),
    durationMinutes: minutes,
    mode: mode,
    counselor: const BookingCounselorRef(id: 'c1', displayName: 'Kaunselor Aina Syazwani'),
    location: location,
    cancellationHours: 24,
    cancellationDeadlineAt: withDeadline ? start.subtract(const Duration(hours: 24)) : null,
    canCancel: canCancel,
    reschedulesLeft: reschedulesLeft,
    canReschedule: canReschedule,
    meeting: meeting ?? (mode == SessionMode.online && status == BookingState.confirmed ? aMeeting(start, minutes) : null),
    place: place,
  );
}

MeetingInfo aMeeting(DateTime start, int minutes, {MeetingState state = MeetingState.opensLater, String? url}) => MeetingInfo(
      state: state,
      opensAt: start.subtract(const Duration(minutes: 15)),
      closesAt: start.add(Duration(minutes: minutes)),
      provider: state == MeetingState.available ? 'Google Meet' : null,
      url: state == MeetingState.available ? (url ?? 'https://meet.example/room-1') : null,
    );

/// What the server says about a slot that can be booked (the details of `GET /bookings/preview`).
BookingDetails someDetails({
  String slot = 's1',
  int days = 1,
  int hour = 10,
  int minutes = 50,
  SessionMode mode = SessionMode.online,
  SlotLocation? location,
  int? hoursToCancel = 24,
  bool insideWindow = false,
  int maxReschedules = 2,
  int linkMinutes = 15,
  int? upcoming,
  int? maxUpcoming,
}) {
  final start = DateTime.utc(2026, 11, 2 + days, hour - 8);

  return BookingDetails(
    slot: slot,
    counselor: const BookingCounselorInfo(id: 'c1', displayName: 'Kaunselor Aina Syazwani'),
    startsAt: start,
    endsAt: start.add(Duration(minutes: minutes)),
    durationMinutes: minutes,
    mode: mode,
    location: location,
    cancellationDeadlineAt: insideWindow ? null : start.subtract(Duration(hours: hoursToCancel ?? 24)),
    cancellationHours: hoursToCancel ?? 24,
    maxReschedules: maxReschedules,
    meetingLinkVisibleMinutes: linkMinutes,
    upcomingSessions: upcoming,
    maxUpcomingSessions: maxUpcoming,
  );
}

CreatedBooking aCreatedBooking({SessionMode mode = SessionMode.online}) => CreatedBooking(
      id: '01bk',
      status: 'confirmed',
      startsAt: DateTime.utc(2026, 11, 3, 2),
      endsAt: DateTime.utc(2026, 11, 3, 2, 50),
      durationMinutes: 50,
      mode: mode,
      counselorName: 'Kaunselor Aina Syazwani',
    );

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
  FakeBookingApi? bookingApi,
  FakeMyBookingsApi? myBookingsApi,
  FakeRequestsApi? requestsApi,
  FakeChatApi? chatApi,
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
    bookingApiProvider.overrideWithValue(bookingApi ?? FakeBookingApi()),
    myBookingsApiProvider.overrideWithValue(myBookingsApi ?? FakeMyBookingsApi()),
    requestsApiProvider.overrideWithValue(requestsApi ?? FakeRequestsApi()),
    chatApiProvider.overrideWithValue(chatApi ?? FakeChatApi()),
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

AvailabilitySlot anAvailabilitySlot(String id, {int days = 0, int hour = 10, int minute = 0, SessionMode mode = SessionMode.online, SlotState state = SlotState.available, SlotLocation? location}) {
  return AvailabilitySlot(id: id, startsAt: DateTime.utc(2026, 11, 2 + days, hour - 8, minute), durationMinutes: 60, mode: mode, state: state, location: location);
}

AvailabilityDay anAvailabilityDay(int days, List<AvailabilitySlot> slots) {
  final date = DateTime.utc(2026, 11, 2 + days);
  final text = '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';

  return AvailabilityDay(date: text, slots: slots);
}

Availability anAvailability(List<AvailabilityDay> days, {List<SessionMode> modes = const [SessionMode.online], String? nextFrom}) {
  return Availability(
    counselor: AvailabilityCounselor(id: 'c1', displayName: 'Kaunselor Aina Syazwani', modesOffered: modes),
    timeZone: 'Asia/Kuala_Lumpur',
    bookingWindow: BookingWindow(earliestStart: DateTime.utc(2026, 11, 2, 3), lastDate: '2026-12-02', minLeadHours: 2),
    days: days,
    nextFrom: nextFrom,
  );
}

// ---- time requests (Mode B) ----

class FakeRequestsApi implements RequestsApi {
  final hoursCalls = <String>[];
  final sends = <({String counselorId, String date, String time, String? message})>[];
  final lists = <int>[];
  final details = <String>[];
  final withdraws = <String>[];

  Future<WorkingHours> Function(String counselorId) hoursHandler = (id) async => aWorkingHours();
  Future<TimeRequest> Function(String counselorId, String date, String time) sendHandler = (id, date, time) async => aTimeRequest(id: 'rq-new');
  Future<RequestsPage> Function(int page) listHandler = (page) async => const RequestsPage(items: [], page: 1, lastPage: 1);
  Future<TimeRequest> Function(String id) detailHandler = (id) async => aTimeRequest(id: id);
  Future<TimeRequest> Function(String id) withdrawHandler = (id) async => aTimeRequest(id: id, status: RequestState.withdrawn);

  @override
  Future<WorkingHours> workingHours(String counselorId) {
    hoursCalls.add(counselorId);

    return hoursHandler(counselorId);
  }

  @override
  Future<TimeRequest> send(String counselorId, {required String date, required String time, String? message}) {
    sends.add((counselorId: counselorId, date: date, time: time, message: message));

    return sendHandler(counselorId, date, time);
  }

  @override
  Future<RequestsPage> list({int page = 1}) {
    lists.add(page);

    return listHandler(page);
  }

  @override
  Future<TimeRequest> detail(String id) {
    details.add(id);

    return detailHandler(id);
  }

  @override
  Future<TimeRequest> withdraw(String id) {
    withdraws.add(id);

    return withdrawHandler(id);
  }
}

/// Aina works Mondays 14:00-16:00 in half-hours in person at Klinik Damai, and Thursdays 10:00-13:00 in hours online.
WorkingHours aWorkingHours({bool accepts = true, List<WorkingHoursLine>? lines}) {
  return WorkingHours(
    counselor: const BookingCounselorRef(id: 'c1', displayName: 'Kaunselor Aina Syazwani'),
    acceptsRequests: accepts,
    hours: lines ??
        const [
          WorkingHoursLine(weekday: 1, from: '14:00', to: '16:00', durationMinutes: 30, mode: SessionMode.physical, location: SlotLocation(name: 'Klinik Damai', city: 'Kuantan')),
          WorkingHoursLine(weekday: 4, from: '10:00', to: '13:00', durationMinutes: 60, mode: SessionMode.online),
        ],
    bookingWindow: BookingWindow(earliestStart: DateTime.utc(2026, 11, 2, 3), lastDate: '2026-12-02', minLeadHours: 2),
    answerWithinHours: 48,
  );
}

/// A request for Thursday 5 Nov at 10:00 Malaysian time (an hour, online), made on [testNow]'s day.
TimeRequest aTimeRequest({
  String id = 'rq1',
  RequestState status = RequestState.pending,
  int days = 3,
  int hour = 10,
  SessionMode mode = SessionMode.online,
  SlotLocation? location,
  String? message,
  String? reason,
  String? bookingId,
}) {
  final start = DateTime.utc(2026, 11, 2 + days, hour - 8);

  return TimeRequest(
    id: id,
    status: status,
    counselor: const BookingCounselorRef(id: 'c1', displayName: 'Kaunselor Aina Syazwani'),
    startsAt: start,
    endsAt: start.add(const Duration(hours: 1)),
    durationMinutes: 60,
    mode: mode,
    location: location,
    message: message,
    responseReason: reason,
    expiresAt: testNow.add(const Duration(hours: 48)),
    bookingId: bookingId,
  );
}

// ---- the chatbot ----

class FakeChatApi implements ChatApi {
  final sends = <({String text, String? session, String clientId})>[];
  final replies = <String>[];
  final ends = <String>[];
  final histories = <int>[];
  final details = <String>[];
  int helplineCalls = 0;

  Future<ChatSendResult> Function(String text, String? session, String clientId) sendHandler = (text, session, clientId) async => aChatResult(text: text, session: session);
  Future<ChatSendResult> Function(String session) replyHandler = (session) async => aChatResult(text: 'earlier', session: session);
  Future<ChatSessionInfo> Function(String session) endHandler = (session) async => aChatSession(id: session, ended: true);
  Future<ChatHistoryPage> Function(int page) historyHandler = (page) async => const ChatHistoryPage(items: [], page: 1, lastPage: 1);
  Future<ChatSessionDetail> Function(String id) detailHandler = (id) async => ChatSessionDetail(session: aChatSession(id: id, ended: true), messages: const []);
  Future<List<Helpline>> Function() helplinesHandler = () async => fallbackHelplines;

  @override
  Future<ChatSendResult> send(String text, {String? sessionId, required String clientId}) {
    sends.add((text: text, session: sessionId, clientId: clientId));

    return sendHandler(text, sessionId, clientId);
  }

  @override
  Future<ChatSendResult> reply(String sessionId) {
    replies.add(sessionId);

    return replyHandler(sessionId);
  }

  @override
  Future<ChatSessionInfo> end(String sessionId) {
    ends.add(sessionId);

    return endHandler(sessionId);
  }

  @override
  Future<ChatHistoryPage> history({int page = 1}) {
    histories.add(page);

    return historyHandler(page);
  }

  @override
  Future<ChatSessionDetail> detail(String sessionId) {
    details.add(sessionId);

    return detailHandler(sessionId);
  }

  @override
  Future<List<Helpline>> helplines() {
    helplineCalls++;

    return helplinesHandler();
  }
}

ChatSessionInfo aChatSession({String id = 's1', bool ended = false, String? emotion, String? preview, int days = 0}) =>
    ChatSessionInfo(id: id, startedAt: testNow.add(Duration(days: days)), ended: ended, endedAt: ended ? testNow.add(Duration(days: days, hours: 1)) : null, dominantEmotion: emotion, preview: preview);

ChatMessage aChatMessage(String id, ChatSender sender, String text, {bool crisis = false}) => ChatMessage(id: id, sender: sender, text: text, sentAt: testNow, crisis: crisis);

/// What the server answers to a sent message: the message stored and, unless [reply] is null, the companion's answer.
ChatSendResult aChatResult({
  required String text,
  String? session,
  String? reply = 'I hear you. That sounds hard.',
  bool unavailable = false,
  bool crisis = false,
  List<Helpline> helplines = const [],
  List<Counselor> counselors = const [],
}) {
  return ChatSendResult(
    session: aChatSession(id: session ?? 's1'),
    message: aChatMessage('m-${text.hashCode}', ChatSender.user, text, crisis: crisis),
    reply: unavailable || reply == null ? null : aChatMessage('r-${text.hashCode}', ChatSender.assistant, reply),
    assistantUnavailable: unavailable,
    crisis: crisis,
    helplines: helplines,
    counselors: counselors,
  );
}
